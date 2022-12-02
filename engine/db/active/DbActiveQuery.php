<?php
/**
 * Author: Drunk
 * Date: 2019/8/29 18:35
 */

namespace dce\db\active;

use dce\base\ExtractType;
use dce\base\SaveMethod;
use dce\db\proxy\Transaction;
use dce\db\Query;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\Statement\SelectStatement;
use dce\db\query\QueryException;
use dce\loader\DecoratorManager;
use dce\model\Model;
use dce\model\ModelException;
use dce\model\validator\ValidatorException;
use drunk\Structure;
use Iterator;
use Throwable;

/** @template T of DbActiveRecord */
class DbActiveQuery extends ActiveQuery {
    private Query $query;

    /** @param class-string<T|DbActiveRecord> $activeRecordClass */
    public function __construct(
        readonly protected string $activeRecordClass,
        readonly private array|null $presetData = null,
    ) {
        $this->query = new Query($activeRecordClass::getProxy());
        $this->query->table($activeRecordClass::getTableName());
    }

    /** @return class-string<T|DbActiveRecord> */
    public function getActiveRecordClass(): string {
        return $this->activeRecordClass;
    }

    /**
     * 设置Where条件
     * @param string|array|RawBuilder|WhereSchema $columnName
     * @param string|int|float|false|RawBuilder|SelectStatement $operator
     * @param string|int|float|array|false|RawBuilder|SelectStatement $value
     * @return self<T>
     */
    public function where(string|array|RawBuilder|WhereSchema $columnName, string|int|float|false|RawBuilder|SelectStatement $operator = false, string|int|float|array|false|RawBuilder|SelectStatement $value = false): self {
        $this->query->where($columnName, $operator, $value);
        return $this;
    }

    /**
     * 设置排序规则
     * @param string|array|RawBuilder $columnName
     * @param string|null $order
     * @return self<T>
     */
    public function order(string|array|RawBuilder $columnName, string|null $order = null): self {
        $this->query->order($columnName, $order);
        return $this;
    }

    /**
     * 设置记录截取量
     * @param int $limit
     * @param int $offset
     * @return self<T>
     */
    public function limit(int $limit, int $offset = 0): self {
        $this->query->limit($limit, $offset);
        return $this;
    }

    /**
     * 取查询字段集
     * @return array
     */
    private function getColumns(): array {
        return array_map(fn($p) => $p->storeName, $this->getActiveRecordClass()::getFieldProperties());
    }

    /**
     * 多记录查询实例化结果集并返回
     * @param string|RawBuilder|null $indexColumn
     * @return list<DbActiveRecord>|list<T>
     * @throws ActiveException|ModelException
     */
    public function select(string|RawBuilder|null $indexColumn = null): array {
        $data = $this->presetData ?? $this->query->select(self::getColumns(), $indexColumn, isAutoRaw: false);
        $data = array_map(fn($datum) => $this->getActiveRecordClass()::from($datum)->markQueriedProperties(), $data);
        if ($data) {
            ($this->withExtends ?? 0) && $this->loadExtendsData($data);
            ($this->withRelations ?? []) && $this->loadWithRelationData($data, $this->withRelations);
        }
        return $data;
    }

    /**
     * 批量查询扩展属性并按主键映射绑定到主题活动记录
     * @param list<DbActiveRecord>|list<T> $recordList
     * @return void
     * @throws ActiveException
     */
    private function loadExtendsData(array $recordList): void {
        $countPks = count(([$pkProperties,, $recordClass, $extendClass] = $this->extendRequirementValid())[0]);
        $keys = range(0, $countPks - 1);
        $indexedPkConditions = array_values(array_reduce($recordList, function($condMap, $record) use($pkProperties, $countPks) {
            $pkValues = $record->getPkValues();
            foreach ($condMap as $pk=>$v) $condMap[$pk][] = $pkValues[$pk];
            return $condMap;
        } , array_reduce($pkProperties, fn($map, $p) => $map + [$p->name => []], [])));
        $where = array_reduce($keys, fn($conds, $i) => array_merge($conds, [[$extendClass::getForeignKeyPropertyByIndex($i)->storeName, 'in', $indexedPkConditions[$i]]]),
            [[$extendClass::getTableProperty()->storeName, $recordClass::$modelId]]);

        [$columnStored, $valueStored] = [$extendClass::getColumnProperty()->storeName, $extendClass::getValueProperty()->storeName];
        $extendData = (new Query($recordClass::getProxy()))->table($extendClass::getTableName())->where($where)->select();
        if ($extendData) foreach ($recordList as $record)
            foreach ($extendData as $extendDatum)
                false !== Structure::arrayMatch($record->getPkValues(true), array_map(fn($i) => $extendDatum[$extendClass::getForeignKeyPropertyByIndex($i)->storeName], $keys), $keys)
                    && $record->setPropertyValue($recordClass::getPropertyById($extendDatum[$columnStored])->name, $extendDatum[$valueStored]);
    }

    /**
     * 批量查询 with 关系数据并按映射关系分配绑定到主体数据
     * @param list<T|DbActiveRecord> $recordList
     * @param array $withRelations
     */
    private function loadWithRelationData(array $recordList, array $withRelations): void {
        /** @var array<string, list<ActiveRecord>> $withViaRelationDataMapping 关系数据映射缓存表，以便节省数据库IO及遍历赋值 */
        $withViaRelationDataMapping = [];
        // 遍历关系名, 批量查询出所有关联关系数据
        foreach ($withRelations as ['relation' => $withRelation, 'children' => $childWithRelations]) {
            // 关系数据若已查询加载过则无需继续处理
            if (key_exists($withRelation->name, $withViaRelationDataMapping)) continue;

            $withRelation->loadWithActiveRecordList($recordList, $withViaRelationDataMapping);

            // 遍历主体对象, 将关系数据挂靠于对象getter属性上
            foreach ($recordList as $record) {
                // 由于对应关系可能是一对多对多, 所以此处将主活动记录集成员转为矩阵, 可以通用化匹配处理多对多的关系数据
                $primaryData = [ $record ];
                foreach ($withRelation->getReversedVias() as $viaRelation) {
                    $viaRelationName = $viaRelation->name;
                    $viaRelationColumns = $viaRelation->getRelationColumns();
                    $viaRelationRecordList = $withViaRelationDataMapping[$viaRelationName];

                    // 筛选与主数据匹配的关联数据集，此数据集即关联关系数据，亦作为下级关联数据的映射依据数据
                    $viaRelationDataMatched = array_reduce($primaryData, function($carry, $primaryDatum) use(& $viaRelationRecordList, $viaRelationColumns) {
                        foreach ($viaRelationRecordList as $foreignDatum) {
                            if (! self::relationRecordMatch($foreignDatum, $primaryDatum, $viaRelationColumns)) continue;
                            $carry[] = $foreignDatum;
//                            unset($viaRelationRecordList[$k]); // 命中则可以删掉以减少循环提升性能（不能删除，否则一对多时只能映射出第一对，所以只能以索引优化）
                            // PHP嵌套循环比对比较耗时，时间复杂度为sum(primary.size * foreign.size)，似乎已无法简单有效的优化方法，后续若有很强的优化需求，可考虑引入索引
                        }
                        return $carry;
                    }, []);

                    $record->setPropertyValue($viaRelationName, $viaRelation->isHasOne() ? ($viaRelationDataMatched[0] ?? null): $viaRelationDataMatched);
                    // 更新依赖关系映射表以便递归查询下一级数据（下个循环）
                    $primaryData = $viaRelationDataMatched;
                }
            }

            $childWithRelations
                && ($childRecordList = array_reduce($recordList, fn($rs, $r) => array_merge($rs,
                    $r->{$withRelation->name} ? (is_array($r->{$withRelation->name}) ? $r->{$withRelation->name} : [$r->{$withRelation->name}]) : []), []))
                && $this->loadWithRelationData($childRecordList, $childWithRelations);
        }
    }

    /**
     * 多记录查询, 返回迭代器, 遍历时实例化为活动记录对象
     * @return Iterator<T>
     * @throws ActiveException
     */
    public function each(): Iterator {
        ($this->withRelations ?? 0) && throw new ActiveException(ActiveException::EACH_NO_SUPPORT_WITH);
        return $this->query->each(self::getColumns(), false,
            fn($datum) => $datum ? $this->getActiveRecordClass()::from($datum)->markQueriedProperties() : false, false);
    }

    /**
     * 筛选一条数据库数据, 转为活动记录对象并返回
     * @return T|false
     * @throws ActiveException
     */
    public function find(): ActiveRecord|false {
        if (! $record = $this->presetData ?? $this->query->find(self::getColumns(), false)) return false;
        $record = $this->getActiveRecordClass()::from($record);
        ($this->withExtends ?? 0) && $this->loadExtendsData([$record]);
        $record->markQueriedProperties();
        return $record;
    }

    /**
     * 查询记录数
     * @return int
     */
    public function count(): int {
        return $this->query->count();
    }

    /**
     * 向数据库插入数据
     * @param ActiveRecord|array|array<array>|array<ActiveRecord> $data
     * @param bool|null $ignoreOrReplace
     * @param SaveMethod|array $method
     * @return int|string
     * @throws ActiveException
     * @throws Throwable
     * @throws ValidatorException
     */
    public function insert(ActiveRecord|array $data, bool|null $ignoreOrReplace = null, SaveMethod|array $method = SaveMethod::Main): int|string {
        $isSingle = $isModelType = $data instanceof ActiveRecord;
        ! $isSingle && ! is_array($firstElem = current($data)) && $isSingle = (! $isModelType = $firstElem instanceof Model);
        $insertData = $isModelType ? array_map(function(Model $m) {
            $m->valid();
            return $m->extract(ExtractType::DbSave, false);
        }, $isSingle ? [$data] : $data) : ($isSingle ? [$data] : $data);
        if ($method === SaveMethod::Main) return $this->query->insert($isSingle ? current($insertData) : $insertData, $ignoreOrReplace);
        $affected = 0;
        $needInsert = in_array($method, [SaveMethod::Both, SaveMethod::BothClean]);
        $countPrimaryKeys = count(([$pkProps] = $this->extendRequirementValid())[0]);
        foreach ($isSingle ? [$data] : $data as $k => $item) {
            $record = $isModelType ? $item : $this->activeRecordClass::from($item);
            if ($needInsert) {
                $insertId = $this->query->insert($insertData[$k], $ignoreOrReplace);
                $affected += $isSingle ? $insertId : 1;
                $countPrimaryKeys === 1 && $pkProps[0]->setValue($record, $insertId, false);
            }
            $extAffected = $this->saveExtends($record, is_array($method) ? $method : (in_array($method, [SaveMethod::Extend, SaveMethod::Both]) ? null : []));
            $affected += $isSingle ? 0 : $extAffected;
        }
        return $affected;
    }

    /**
     * 向数据库插入数据，若有唯一键冲突则更新
     * @throws Throwable
     * @throws ActiveException
     * @throws ValidatorException
     */
    public function insertUpdate(ActiveRecord|array $record, SaveMethod|array $method = SaveMethod::Main): int {
        is_array($record) ? $record = $this->activeRecordClass::from($record) : $record->valid();
        if (! in_array($method, [SaveMethod::Extend, SaveMethod::ExtendClean])) {
            $insertId = $this->query->insertUpdate($record->extract(ExtractType::DbSave));
            if ($insertId) $this->activeRecordClass::getPkProperties()[0]->setValue($record, $insertId, false);
        }
        if (SaveMethod::Main !== $method) {
            $this->extendRequirementValid();
            $this->saveExtends($record, is_array($method) ? $method : (in_array($method, [SaveMethod::Extend, SaveMethod::Both]) ? null : []));
        }
        return current($record->getPkValues());
    }

    /**
     * 更新筛选的数据
     * @param ActiveRecord|array $targetRecord
     * @param bool|null $allowEmptyConditionOrMustEqual
     * @param SaveMethod|array $method
     * @param array $columns 仅更新指定字段
     * @return int
     * @throws ActiveException
     * @throws ModelException
     * @throws Throwable
     * @throws ValidatorException
     */
    public function update(ActiveRecord|array $targetRecord, bool|null $allowEmptyConditionOrMustEqual = false, SaveMethod|array $method = SaveMethod::Main, array $columns = []): int {
        $affected = 0;
        ($isArray = is_array($targetRecord)) && $targetRecord = $this->activeRecordClass::from($targetRecord);
        if ($columns) {
            array_push($columns, ... array_map(fn($pkp) => $pkp->name, $this->activeRecordClass::getPkProperties()));
            $scenario = DecoratorManager::getRefPropertyValue(Model::class, 'scenario', $targetRecord);
            [$targetRecord, $originRecord] = [new ($this->activeRecordClass)($scenario), $targetRecord];
            foreach ($columns as $column) $targetRecord->setPropertyValue($column, $originRecord->{$column});
        }
        // 只有传入的activeRecord才自动校验，因为自动生成的无法得知scenario
        ! $isArray && $targetRecord->valid();
        ! in_array($method, [SaveMethod::Extend, SaveMethod::ExtendClean]) && $affected += $this->query->update($targetRecord->extract(ExtractType::DbSave), $allowEmptyConditionOrMustEqual);
        if (SaveMethod::Main !== $method) {
            $this->extendRequirementValid();
            foreach ($this->presetData ? [$this->activeRecordClass::from($this->presetData)] : $this->select() as $record)
                $affected += $this->saveExtends($record, is_array($method) ? $method : (in_array($method, [SaveMethod::Extend, SaveMethod::Both]) ? null : []), $targetRecord);
        }
        return $affected;
    }

    /**
     * 扩展查询必须条件校验
     * @return array
     * @throws ActiveException
     */
    private function extendRequirementValid(): array {
        ! $this->activeRecordClass::getExtProperties() && throw new ActiveException(ActiveException::NO_EXT_PROPERTY_DEFINED);
        $arc = $this->activeRecordClass;
        $ec = $arc::getExtendClass();
        $pkProps = $arc::getPkProperties();
        $efkProps = $ec::getForeignKeyProperties();
        $countPrimaryKeys = count($pkProps);
        $countForeignKeys = count($efkProps);
        ($countPrimaryKeys > $countForeignKeys || $countPrimaryKeys < 1) && throw new ActiveException(ActiveException::NO_PK_OR_TOO_MANY_PKS);
        return [$pkProps, $efkProps, $arc, $ec];
    }

    /**
     * 持久化扩展数据
     * @param ActiveRecord $pkRecord
     * @param array|null $columns {null: 存全部字段, []: 存全部字段，并自动删除之外的字段, [...]: 仅存指定字段}
     * @param ActiveRecord|null $targetRecord
     * @return int
     */
    private function saveExtends(ActiveRecord $pkRecord, array|null $columns = null, ActiveRecord $targetRecord = null): int {
        $arc = $this->activeRecordClass;
        $extendClass = $arc::getExtendClass();
        $extendColumnName = $extendClass::getColumnProperty()->storeName;
        $extendValueName = $extendClass::getValueProperty()->storeName;
        $extProperties = $columns ? array_filter($arc::getExtProperties(), fn($p) => in_array($p->name, array_map([static::class, 'toModelKey'], $columns))) : $arc::getExtProperties();
        $pkValues = $pkRecord->getPkValues(true);
        $whereMapping = array_reduce(array_keys($pkValues), fn($map, $i) => $map + [$extendClass::getForeignKeyPropertyByIndex($i)->storeName => $pkValues[$i]],
            [$extendClass::getTableProperty()->storeName => $arc::$modelId]);

        $targetColumnIds = $targetData = [];
        $targetRecord ??= $pkRecord;
        foreach ($extProperties as $extProperty) {
            if (! isset($targetRecord->{$extProperty->name})) continue;
            $targetColumnIds[] = $extProperty->id;
            $targetData[] = $extendClass::from($whereMapping + [$extendColumnName => $extProperty->id, $extendValueName => $targetRecord->{$extProperty->name}]);
        }
        $emptyColumns = $columns === [];
        if (! $targetColumnIds) {
            if (! $emptyColumns) return 0;
            $targetColumnIds[] = 0; // 若待存对象无有值扩展属性，且储存方式需自动删除之外的字段，则清除所有扩展字段
        }

        // 若指定了字段，则仅插入/更新这些字段，否则插入/更新全部扩展字段，且若传入空数组，则自动删除没在其列的字段
        $emptyColumns && $extendClass::query()->where(array_merge(ActiveQuery::mappingToWhere($whereMapping), [[$extendColumnName, 'not in', $targetColumnIds]]))->delete();
        return $targetData ? $extendClass::query()->insert($targetData, false) : 0;
    }

    /**
     * 删除筛选中的数据
     * @param bool|null $allowEmptyConditionOrMustEqual
     * @param array $extColumns
     * @return int
     * @throws ActiveException
     * @throws ModelException
     * @throws QueryException
     */
    public function delete(bool|null $allowEmptyConditionOrMustEqual = false, array $extColumns = []): int {
        $this->query->getQueryBuilder()->getWhereSchema()->isEmpty() && throw new QueryException(QueryException::EMPTY_DELETE_FULL_NOT_ALLOW);
        $arc = $this->activeRecordClass;
        $affected = 0;
        if ($arc::getExtProperties()) {
            $extendClass = $arc::getExtendClass();
            $extFKProps = $extendClass::getForeignKeyProperties();
            $where = array_merge(
                [[$extendClass::getTableProperty()->storeName, $arc::$modelId]],
                array_reduce($this->presetData ? [$arc::from($this->presetData)] : $this->select(), function($w, $r) use($arc, $extFKProps) {
                    foreach ($arc::getPkProperties() as $index => $pkProperty) {
                        $w[$index] ??= [$extFKProps[$index]->storeName, 'in', []];
                        $w[$index][2][] = $r->{$pkProperty->name};
                    }
                    return $w;
                }, []),
                $extColumns ? [[$extendClass::getColumnProperty()->storeName, 'in', array_map(fn($c) => $arc::getProperty($arc::toModelKey($c))->id, $extColumns)]] : []
            );
            $affected += (new Query($arc::getProxy()))->table($extendClass::getTableName())->where($where)->delete();
            if ($extColumns) return $affected; // 若指定了需删的扩展字段，则直接返回
        }
        return $affected + $this->query->delete(null, $allowEmptyConditionOrMustEqual);
    }

    /**
     * 开启事务
     * @return Transaction
     */
    public function begin(): Transaction {
        return $this->query->begin();
    }
}
