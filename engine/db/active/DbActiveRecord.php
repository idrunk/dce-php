<?php
/**
 * Author: Drunk
 * Date: 2019/10/17 11:54
 */

namespace dce\db\active;

use dce\base\ExtractType;
use dce\base\FindMethod;
use dce\base\SaveMethod;
use dce\db\entity\DbField;
use dce\db\proxy\DbProxy;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\QueryException;
use dce\model\ModelException;
use dce\model\validator\ValidatorException;
use Throwable;

abstract class DbActiveRecord extends ActiveRecord {
    /** @inheritDoc */
    protected static string $fieldClass = DbField::class;

    /**
     * 指定目标库或设定查询代理器
     * @return string|DbProxy|null
     */
    public static function getProxy(): string|DbProxy|null {
        return null;
    }

    /**
     * 取一个新的DbActiveQuery实例
     * @param array|null $presetData
     * @return DbActiveQuery<class-string<static>>
     */
    public static function query(array|null $presetData = null): DbActiveQuery {
        return new DbActiveQuery(static::class, $presetData);
    }

    /**
     * 筛选一条数据库数据, 转为活动记录对象并返回
     * @param int|string|array $condition
     * @param FindMethod $method
     * @return false|static
     * @throws ActiveException
     */
    public static function find(int|string|array $condition, FindMethod $method = FindMethod::Main): static|false {
        $mapping = self::scalarToMapping($condition);
        if (! in_array($method, [FindMethod::Main, FindMethod::Extend, FindMethod::Both])) {
            $record = self::findCache($mapping);
            if (! $record) {
                if (! $deepMethod = match($method) {
                    FindMethod::MainCacheDeep => FindMethod::Main,
                    FindMethod::ExtendCacheDeep => FindMethod::Extend,
                    FindMethod::BothCacheDeep => FindMethod::Both,
                    default => false,
                }) return false;
                $record = self::find($mapping, $deepMethod);
                $record && $record->saveCache();
            }
            return $record;
        }
        return self::query($method !== FindMethod::Extend ? null : $mapping)->where(ActiveQuery::mappingToWhere($mapping))->withExtends($method !== FindMethod::Main)->find();
    }

    /**
     * find参数转主键值mapping
     * @param int|string|array $where
     * @return array<string, mixed>
     * @throws ActiveException
     */
    private static function scalarToMapping(int|string|array $where): array {
        ! ($isScalar = is_scalar($where)) && (! is_array($where) || array_is_list($where)) && throw new ActiveException(ActiveException::FIND_WHERE_MUST_BE_SCALAR_OR_MAPPING);
        $isScalar && $where = [self::getPkNames()[0] => $where];
        return $where;
    }

    /**
     * 活动记录持久化
     * @param bool $needLoadNew
     * @param bool|null $ignoreOrReplace {true: insert ignore into, false: replace into , null: insert into}
     * @param SaveMethod|array<string> $method
     * @return int|string
     * @throws ActiveException
     * @throws Throwable
     * @throws ValidatorException
     */
    public function insert(bool $needLoadNew = false, bool|null $ignoreOrReplace = null, SaveMethod|array $method = SaveMethod::Main): int|string {
        $this->valid();
        $insertId = self::query()->insert($this, $ignoreOrReplace, $method);
        $needLoadNew ? $this->apply(static::find($insertId)->extract(ExtractType::KeepKey, false))
            : $insertId > 0 && self::getPkProperties()[0]->setValue($this, $insertId, false);
        $this->markQueriedProperties();
        $this->saveCache();
        return $insertId;
    }

    /**
     * 插入数据，若已存在则更新
     * @param SaveMethod|array $method
     * @return int
     * @throws ActiveException
     * @throws Throwable
     */
    public function insertUpdate(SaveMethod|array $method = SaveMethod::Main): int {
        $affected = self::query()->insertUpdate($this, $method);
        $this->saveCache();
        return $affected;
    }

    /**
     * 持久化更新活动记录
     * @param SaveMethod|array $method
     * @param array $columns 仅更新指定字段
     * @return int
     * @throws ActiveException
     * @throws Throwable
     * @throws ValidatorException
     */
    public function update(SaveMethod|array $method = SaveMethod::Main, array $columns = []): int {
        ! $this->isCreateByQuery() && throw new ActiveException(ActiveException::CANNOT_UPDATE_BEFORE_SAVE);
        $affected = self::query($this->getOriginalProperties())->where($this->genPropertyConditions())->update($this, false, $method);
        $affected > 0 && $this->saveModifyRecord();
        $this->saveCache();
        return $affected;
    }

    /**
     * 删除数据库记录
     * @param array $extColumns
     * @return int
     * @throws ActiveException
     * @throws QueryException
     * @throws ModelException
     */
    public function delete(array $extColumns = []): int {
        ! $this->isCreateByQuery() && throw new ActiveException(ActiveException::CANNOT_DELETE_BEFORE_SAVE);
        self::getCacheProperties() && static::getCacheClass()::delete(self::genCacheKey($this->getPkValues(true)));
        $affected = self::query($this->getOriginalProperties())->where($this->genPropertyConditions())->delete(false, $extColumns);
        $affected > 0 && $this->saveModifyRecord(true);
        return $affected;
    }

    /** @return class-string<CacheEngine> */
    protected static function getCacheClass(): string {
        return CacheEngine::class;
    }

    /** @return class-string<DceExtendColumn> */
    public static function getExtendClass(): string {
        return DceExtendColumn::class;
    }

    /** @return class-string<DceModifyRecord> */
    public static function getModifyRecordClass(): string {
        return DceModifyRecord::class;
    }

    private static function findCache(array $pkMapping): static|false {
        $pkMapping = array_reduce(self::getPkNames(), fn($w, $n) => $w + [$n => $pkMapping[$n]], []); // 调整PK顺序以便生成缓存键
        $cacheClass = self::getCacheClass();
        $record = $cacheClass::load(self::genCacheKey($pkMapping));
        if (! $record) return false;
        return static::from($pkMapping + $cacheClass::decodeKeys($record, static::class));
    }

    protected static function genCacheKey(array $pkMapping): string {
        return implode(':', ['arc', static::$modelId, ... $pkMapping]);
    }

    private function saveCache(): void {
        if (! $cacheableProperties = self::getCacheProperties()) return;
        $data = array_reduce($cacheableProperties, fn($m, $p) => ($v = $p->getValue($this, false)) === false ? $m : $m + [$p->id => $v], []);
        $data && self::getCacheClass()::save(self::genCacheKey($this->getPkValues(true)), $data);
    }

    /**
     * 加载扩展数据，并返回活动记录对象
     * @param bool $findCache
     * @return $this
     * @throws ActiveException
     */
    public function loadExtends(bool $findCache = true): static {
        if ($findCache) {
            foreach (self::getCacheClass()::load(self::genCacheKey($this->getPkValues(true))) as $columnId => $value) {
                if (! $property = static::getPropertyById($columnId)) continue;
                $this->setPropertyValue($property->name, $value);
            }
            return $this;
        }
        ! static::getExtProperties() && throw new ActiveException(ActiveException::NO_EXT_PROPERTY_DEFINED);
        return self::query(ActiveQuery::mappingToWhere($this->genExternalMapping(static::getExtendClass())))->withExtends()->find();
    }

    /**
     * 取外置记录主键值映射表
     * @param class-string<DceExternalRecord> $externalClass
     * @return array
     * @throws ActiveException
     */
    private function genExternalMapping(string $externalClass): array {
        $pkValues = $this->getPkValues(true);
        $countForeignKeys = count($externalClass::getForeignKeyProperties());
        $countPkValues = count($pkValues);
        ($countPkValues > $countForeignKeys || $countPkValues < 1) && throw new ActiveException(ActiveException::NO_PK_OR_TOO_MANY_PKS);
        return array_reduce(array_keys($pkValues), fn($map, $i) => $map + [$externalClass::getForeignKeyPropertyByIndex($i)->storeName => $pkValues[$i]],
            [$externalClass::getTableProperty()->storeName => static::$modelId]);
    }

    /**
     * 保存修改记录
     * @param bool $isDelete
     * @return int
     * @throws ActiveException
     */
    private function saveModifyRecord(bool $isDelete = false): int {
        if (! $recordProperties = static::getModifyRecordProperties()) return 0;
        $recordClass = static::getModifyRecordClass();
        $newValueMapping = $isDelete ? [] : array_reduce($recordProperties, fn($vs, $p) => array_replace($vs, ($v = $p->getValue($this, false)) === false ? [] : [$p->id => $v]), []);
        $originalValues = $this->getOriginalProperties();
        $originalRecord = array_reduce($recordProperties, fn($vs, $p) => array_replace($vs,
            ! key_exists($p->name, $originalValues) || $originalValues[$p->name] === false || $originalValues[$p->name] === ($newValueMapping[$p->id] ?? null)
                ? [] : [$p->id => $originalValues[$p->name]]), []);
        if (! $originalRecord) return 0;

        $recordData = $this->genExternalMapping($recordClass);
        $where = ActiveQuery::mappingToWhere($recordData);
        $recordData[$versionName = $recordClass::getVersionProperty()->storeName] = ($recordClass::query()->where($where)->order($versionName, 'desc')->find()->version ?? 0) + 1;
        $recordData[$recordClass::getOriginalRecordProperty()->storeName] = json_encode($originalRecord, JSON_UNESCAPED_UNICODE);
        return $recordClass::from($recordData)->insert();
    }
}
