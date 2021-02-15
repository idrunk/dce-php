<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/7/11 11:34
 */

namespace dce\db\active;

use dce\db\proxy\Transaction;
use dce\model\Model;

abstract class ActiveRecord extends Model {
    /** @var bool 是否通过查询创建的活动记录实例 */
    private bool $createByQuery = false;

    /** @var array 记录原始属性 (主用于更新等操作时根据原始值查询数据库) */
    private array $originalProperties;

    /** @var ActiveQuery|null 活动记录查询器 */
    protected ActiveQuery|null $activeQuery = null;

    /**
     * 取主键值集
     * @return array
     */
    public function getPkValues(): array {
        $properties = [];
        $pks = static::getPkFields();
        foreach ($pks as $pk) {
            $properties[$pk] = $this->$pk;
        }
        return $properties;
    }

    /**
     * 处理Getter值 (取关联数据)
     * @param string $name
     * @param mixed $value
     * @return ActiveRecord|array|false|null
     */
    protected function handleGetter(string $name, mixed $value): ActiveRecord|array|false|null {
        if ($value instanceof ActiveRelation) {
            $value = $value->screen();
        }
        return $value;
    }

    /**
     * 初始化查询结果活动记录实例 (对新实例属性赋值)
     * @param array $properties
     */
    public function setQueriedProperties(array $properties): void {
        $this->clearGetterValues()->createByQuery = true;
        static::applyProperties($properties);
        $this->originalProperties = $this->extractProperties();
    }

    /**
     * 是否查询结果实例
     * @return bool
     */
    public function isCreateByQuery(): bool {
        return $this->createByQuery;
    }

    /**
     * 通过属性值生成查询条件
     * @return array
     */
    protected function genPropertyConditions(): array {
        return self::hashToWhere($this->originalProperties);
    }

    /**
     * 将模型属性键值表转查询条件
     * @param array $hash
     * @return array
     */
    protected static function hashToWhere(array $hash): array {
        $conditions = [];
        foreach ($hash as $k => $v) {
            $conditions[] = [$k, '=', $v];
        }
        return $conditions;
    }

    /**
     * 设置活动记录查询器
     * @param ActiveQuery $activeQuery
     * @return $this
     */
    private function setActiveQuery(ActiveQuery $activeQuery): static {
        $this->activeQuery = $activeQuery;
        return $this;
    }

    /**
     * 保存数据 (插入或更新数据库)
     * @return int|string
     */
    public function save(): int|string {
        if ($this->createByQuery) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * 删除数据库记录
     * @return int
     * @throws ActiveException
     */
    public function delete(): int {
        if (! $this->createByQuery) {
            throw new ActiveException('当前对象尚未保存，无法删除');
        }
        $where = $this->genPropertyConditions();
        return $this->getActiveQuery()->where($where)->delete();
    }

    /**
     * 构建关联活动记录查询器
     * @param string $className
     * @param array $relationMap
     * @param string|null $viaRelationName
     * @return ActiveRelation
     * @throws ActiveException
     */
    private function buildRelation(string $className, array $relationMap, string|null $viaRelationName): ActiveRelation {
        $activeRecord = new $className;
        if (! $activeRecord instanceof ActiveRecord) {
            throw new ActiveException("{$className} 必须继承自 " . self::class);
        }
        $activeQueryClass = $this->getActiveQuery()::class;
        $activeQuery = new $activeQueryClass($activeRecord);
        $activeRecord->setActiveQuery($activeQuery);
        $activeRelation = new ActiveRelation($activeQuery);
        return $activeRelation->setMapping($this, $relationMap, $viaRelationName);
    }

    /**
     * 构建一对一关联数据查询器
     * @param string $className
     * @param array $relationMap
     * @param string|null $viaRelationName
     * @return ActiveRelation
     * @throws ActiveException
     */
    public function hasOne(string $className, array $relationMap, string|null $viaRelationName = null): ActiveRelation {
        return $this->buildRelation($className, $relationMap, $viaRelationName)->hasOne();
    }

    /**
     * 构建一对多关联数据查询器
     * @param string $className
     * @param array $relationMap
     * @param string|null $viaRelationName
     * @return ActiveRelation
     * @throws ActiveException
     */
    public function hasMany(string $className, array $relationMap, string|null $viaRelationName = null): ActiveRelation {
        return $this->buildRelation($className, $relationMap, $viaRelationName);
    }

    /**
     * 开启事务
     * @return Transaction
     */
    public static function begin(): Transaction {
        return static::query()->begin();
    }

    /**
     * 取表名 (子类可覆盖指定表名)
     * @return string
     */
    public static function getTableName(): string {
        static $tableName;
        if (null === $tableName) {
            $tableName = preg_replace('/^.*\b(\w+?)$/u', '$1', static::class);
            $tableName = static::toDbKey($tableName);
        }
        return $tableName;
    }

    /**
     * 取新的活动记录查询器实例
     * @return ActiveQuery
     */
    abstract public static function query(): ActiveQuery;

    /**
     * 取当前查询器实例
     * @return ActiveQuery
     */
    abstract protected function getActiveQuery(): ActiveQuery;

    /**
     * 向数据库插入数据
     * @return int|string
     */
    abstract public function insert(): int|string;

    /**
     * 将当前对象更新到数据库
     * @return int
     */
    abstract public function update(): int;
}
