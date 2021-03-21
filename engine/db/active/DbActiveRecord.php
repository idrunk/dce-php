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

abstract class DbActiveRecord extends ActiveRecord {
    /** @inheritDoc */
    protected static string $fieldClass = DbField::class;

    /** @var array 数据表主键名集 */
    private static array $primaryKeysMapping = [];

    /**
     * 筛选一条数据库数据, 转为活动记录对象并返回
     * @param int|string|array|RawBuilder|WhereSchema $whereCondition
     * @return false|static
     */
    public static function find(int|string|array|RawBuilder|WhereSchema $whereCondition): static|false {
        $instance = new static;
        if (is_scalar($whereCondition)) {
            $pks = static::getPkNames();
            if (count($pks) > 1) {
                return false;
            }
            $whereCondition = [[$pks[0], '=', $whereCondition]];
        }
        return $instance->getActiveQuery()->where($whereCondition)->find();
    }

    /** @inheritDoc */
    protected function getActiveQuery(): DbActiveQuery {
        if (! $this->activeQuery) {
            $this->activeQuery = new DbActiveQuery($this);
        }
        return $this->activeQuery;
    }

    /** @inheritDoc */
    public function insert(bool $needLoadNew = false): int|string {
        $this->valid();
        $data = $this->extractProperties();
        $insertId = $this->getActiveQuery()->insert($data);
        $pk = static::getPkNames();
        if ($insertId && count($pk) === 1) {
            if ($needLoadNew) {
                // 查出数据库中真正储存的数据填充到当前属性, 如插入NOW()则取到插入的实时时间
                $data = static::find($insertId)->extractProperties();
            } else {
                $data[$pk[0]] = $insertId;
            }
            $this->setQueriedProperties($data);
        }
        return $insertId;
    }

    /** @inheritDoc */
    public function update(): int {
        $this->valid();
        $data = $this->extractProperties();
        $where = $this->genPropertyConditions();
        return $this->getActiveQuery()->where($where)->update($data);
    }

    /**
     * 计数器更新
     * @param string|array $fieldName
     * @param int|float $increment 正数自增/负数自减
     * @return int
     */
    public function updateCounter(string|array $fieldName, int|float $increment = 1): int {
        $counters = is_array($fieldName) ? $fieldName : [$fieldName => $increment];
        $data = [];
        foreach ($counters as $fieldName => $increment) {
            $increment = (float) $increment;
            $data[SchemaAbstract::tableWrapThrow($fieldName)] = new RawBuilder("{$fieldName} + {$increment}", false);
        }
        $where = $this->genPropertyConditions();
        return $this->getActiveQuery()->where($where)->update($data);
    }

    /**
     * 指定目标库或设定查询代理器
     * @return string|DbProxy|null
     */
    public static function getProxy(): string|DbProxy|null {
        return null;
    }

    /** @inheritDoc */
    public static function query(): DbActiveQuery {
        return (new static)->getActiveQuery();
    }

    /**
     * 取数据表主键集
     * @return array
     */
    public static function getPkNames(): array {
        if (! key_exists(static::class, self::$primaryKeysMapping)) {
            self::$primaryKeysMapping[static::class] = [];
            foreach (static::getFields() as $field) {
                if ($field->isPrimaryKey()) {
                    self::$primaryKeysMapping[static::class][] = $field->getName();
                }
            }
        }
        return self::$primaryKeysMapping[static::class];
    }
}
