<?php
/**
 * Author: Drunk
 * Date: 2019/10/17 11:54
 */

namespace dce\db\active;

use dce\db\entity\DbField;
use dce\db\proxy\DbProxy;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\QueryException;

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
     * @return DbActiveQuery<class-string<static>>
     */
    public static function query(): DbActiveQuery {
        return new DbActiveQuery(static::class);
    }

    /**
     * 筛选一条数据库数据, 转为活动记录对象并返回
     * @param int|string|array|RawBuilder|WhereSchema $whereCondition
     * @return false|static
     */
    public static function find(int|string|array|RawBuilder|WhereSchema $whereCondition): static|false {
        if (is_scalar($whereCondition)) {
            $pks = static::getPkNames();
            if (count($pks) > 1) return false;
            $whereCondition = [[$pks[0], '=', $whereCondition]];
        }
        return self::query()->where($whereCondition)->find();
    }

    /** @inheritDoc */
    public function insert(bool $needLoadNew = false, bool|null $ignoreOrReplace = null): int|string {
        $this->valid();
        $data = $this->extract(true, false);
        $insertId = self::query()->insert($data, $ignoreOrReplace);
        $pk = static::getPkNames();
        if ($insertId && count($pk) === 1) {
            if ($needLoadNew) {
                // 查出数据库中真正储存的数据填充到当前属性, 如插入NOW()则取到插入的实时时间
                $data = static::find($insertId)->extract(true, false);
            } else {
                $data[$pk[0]] = $insertId;
            }
            $this->setQueriedProperties($data);
        }
        return $insertId;
    }

    /** @inheritDoc */
    public function update(array $columns = []): int {
        $this->valid();
        $data = $this->extract(true, false);
        if ($columns && ($columns = array_map([static::class, 'toDbKey'], $columns)))
            $data = array_filter($data, fn($column) => in_array($column, $columns), ARRAY_FILTER_USE_KEY);
        $where = $this->genPropertyConditions();
        return self::query()->where($where)->update($data);
    }

    /**
     * 计数器更新
     * @param string|array $fieldName
     * @param int|float $increment 正数自增/负数自减
     * @return int
     * @throws QueryException
     */
    public function updateCounter(string|array $fieldName, int|float $increment = 1): int {
        $counters = is_array($fieldName) ? $fieldName : [$fieldName => $increment];
        $data = [];
        foreach ($counters as $fieldName => $increment) {
            $increment = (float) $increment;
            $data[SchemaAbstract::tableWrapThrow($fieldName)] = new RawBuilder("$fieldName + $increment", false);
        }
        $where = $this->genPropertyConditions();
        return self::query()->where($where)->update($data);
    }
}
