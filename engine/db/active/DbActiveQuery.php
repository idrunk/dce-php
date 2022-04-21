<?php
/**
 * Author: Drunk
 * Date: 2019/8/29 18:35
 */

namespace dce\db\active;

use dce\db\proxy\Transaction;
use dce\db\Query;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\Statement\SelectStatement;
use dce\model\Model;
use dce\model\ModelException;
use Iterator;

/** @template T of DbActiveRecord */
class DbActiveQuery extends ActiveQuery {
    private Query $query;

    /** @param class-string<T|DbActiveRecord> $activeRecordClass */
    public function __construct(
        readonly protected string $activeRecordClass,
    ) {
        $this->query = new Query($this->getActiveRecordClass()::getProxy());
        $this->query->table($this->getActiveRecordClass()::getTableName());
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
        return array_map(fn($f) => $f->getName(), $this->getActiveRecordClass()::getFields());
    }

    /**
     * 多记录查询实例化结果集并返回
     * @param string|RawBuilder|null $indexColumn
     * @return list<DbActiveRecord>|list<T>
     * @throws ActiveException|ModelException
     */
    public function select(string|RawBuilder|null $indexColumn = null): array {
        $data = $this->query->select(self::getColumns(), $indexColumn, isAutoRaw: false);
        $data = array_map(fn($datum) => (new ($this->getActiveRecordClass()))->setQueriedProperties($datum), $data);
        return $this->loadWithRelationData($data);
    }

    /**
     * 批量查询 with 关系数据并按映射关系分配绑定到主体数据
     * @param list<T|DbActiveRecord> $recordList
     * @return array
     * @throws ActiveException|ModelException
     */
    private function loadWithRelationData(array $recordList): array {
        if (! isset($this->withRelations) || ! $recordList) return $recordList;

        /** @var array<string, list<ActiveRecord>> $withViaRelationDataMapping 关系数据映射缓存表，以便节省数据库IO及遍历赋值 */
        $withViaRelationDataMapping = [];
        // 遍历关系名, 批量查询出所有关联关系数据
        foreach ($this->withRelations as $withRelation) {
            // 关系数据若已查询加载过则无需继续处理
            if (key_exists($withRelation->getName(), $withViaRelationDataMapping)) continue;

            $withRelation->loadWithActiveRecordList($recordList, $withViaRelationDataMapping);
            $withViaRelationDataMappingCopy = $withViaRelationDataMapping;

            // 遍历主体对象, 将关系数据挂靠于对象getter属性上
            foreach ($recordList as $record) {
                // 由于对应关系可能是一对多对多, 所以此处将主活动记录集成员转为矩阵, 可以通用化匹配处理多对多的关系数据
                $primaryData = [ $record ];
                foreach ($withRelation->getReversedVias() as $viaRelation) {
                    $viaRelationName = $viaRelation->getName();
                    $viaRelationColumns = $viaRelation->getRelationColumns();
                    $viaRelationRecordList = & $withViaRelationDataMappingCopy[$viaRelationName];

                    // 筛选与主数据匹配的关联数据集，此数据集即关联关系数据，亦作为下级关联数据的映射依据数据
                    $viaRelationDataMatched = array_reduce($primaryData, function($carry, $primaryDatum) use(& $viaRelationRecordList, $viaRelationColumns) {
                        foreach ($viaRelationRecordList as $k => $foreignDatum) {
                            if (! self::relationRecordMatch($foreignDatum, $primaryDatum, $viaRelationColumns)) continue;
                            $carry[] = $foreignDatum;
                            unset($viaRelationRecordList[$k]); // 命中则可以删掉以减少循环提升性能
                            // PHP嵌套循环比对比较耗时，时间复杂度为sum(primary.size * foreign.size)，似乎已无法简单有效的优化方法，后续若有很强的优化需求，可考虑引入索引
                        }
                        return $carry;
                    }, []);

                    $record->setPropertyValue($viaRelationName, $viaRelation->isHasOne() ? ($viaRelationDataMatched[0] ?? null): $viaRelationDataMatched);
                    // 更新依赖关系映射表以便递归查询下一级数据（下个循环）
                    $primaryData = $viaRelationDataMatched;
                }
            }
        }
        return $recordList;
    }

    /**
     * 多记录查询, 返回迭代器, 遍历时实例化为活动记录对象
     * @return Iterator<T>
     * @throws ActiveException
     */
    public function each(): Iterator {
        $this->withRelations && throw new ActiveException(ActiveException::EACH_NO_SUPPORT_WITH);
        return $this->query->each(self::getColumns(), false,
            fn($datum) => $datum ? (new ($this->getActiveRecordClass()))->setQueriedProperties($datum) : false, false);
    }

    /**
     * 筛选一条数据库数据, 转为活动记录对象并返回
     * @return T|false
     */
    public function find(): ActiveRecord|false {
        $data = $this->query->find(self::getColumns(), false);
        return $data ? (new ($this->getActiveRecordClass()))->setQueriedProperties($data) : false;
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
     * @param array $data
     * @param bool|null $ignoreOrReplace
     * @return int|string
     */
    public function insert(array $data, bool|null $ignoreOrReplace = null): int|string {
        // 因为insert方法支持批量插入，而插入的数据有时为了方便需传递活动记录对象组，若为活动记录则需转为数据库式蛇底的数组下标字段名
        current($data) instanceof Model && $data = array_map(function(Model $m) {
            $m->valid();
            return $m->extract(true, false);
        }, $data);
        return $this->query->insert($data, $ignoreOrReplace);
    }

    /**
     * 更新筛选的数据
     * @param array $data
     * @return int
     */
    public function update(array $data): int {
        return $this->query->update($data, false);
    }

    /**
     * 删除筛选中的数据
     * @return int
     */
    public function delete(): int {
        return $this->query->delete(null, false);
    }

    /**
     * 开启事务
     * @return Transaction
     */
    public function begin(): Transaction {
        return $this->query->begin();
    }
}
