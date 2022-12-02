<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/7/11 11:34
 */

namespace dce\db\active;

use dce\base\CoverType;
use dce\db\entity\Field;
use dce\db\proxy\Transaction;
use dce\model\Model;
use dce\model\ModelException;
use dce\model\Property;
use drunk\Structure;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;

abstract class ActiveRecord extends Model {
    /** @var class-string<Field> */
    protected static string $fieldClass;

    /** @var string 实体储存名前缀 */
    protected static string $prefix;

    /** @var array<string, Property[]> 活动记录字段集 */
    private static array $fieldProperties = [];

    /** @var array<string, Property[]> 活动记录主键集 */
    private static array $pkProperties = [];

    /** @var array<string, array<string, ActiveRelation>> 活动记录关系表 */
    private static array $relationMapping = [];

    /** @var bool 是否通过查询创建的活动记录实例 */
    private bool $createByQuery = false;

    /** @var array 记录原始属性 (主用于更新等操作时根据原始值查询数据库) */
    private array $originalProperties;

    /** @var ActiveQuery 活动记录查询器 */
    protected ActiveQuery $activeQuery;

    /**
     * 取主键值集
     * @param bool $pureValue {true: 纯值数组, false: 字段为下标的数组}
     * @return array
     */
    public function getPkValues(bool $pureValue = false): array {
        return array_reduce(static::getPkNames(), fn($carry, $pk) => array_merge($carry, [$pureValue ? 0 : $pk => $this->$pk]), []);
    }

    /** @inheritDoc */
    protected function handleGetter(string $name, mixed $value): ActiveRecord|array|string|int|float|false|null {
        $value instanceof ActiveRelation && $value = $value->screen($this);
        return $value;
    }

    /** @inheritDoc */
    protected function setGetterValue(string $name, mixed $value, CoverType $coverType = CoverType::Replace): void {
        if ($value && $relation = self::getActiveRelation($name)) {
            if ($relation->isHasOne()) {
                is_array($value) && $value = $relation->foreignActiveRecordClass::from($value, $coverType);
            } else if (is_array($value[0] ?? 0)) {
                $value = array_map(fn($item) => $relation->foreignActiveRecordClass::from($item, $coverType), $value);
            }
        }
        parent::setGetterValue($name, $value, $coverType);
    }

    /** @inheritDoc */
    protected function getGetterValue(string $name, array $extractArgs = []): mixed {
        if (($value = parent::getGetterValue($name, $extractArgs)) && $extractArgs && $relation = self::getActiveRelation($name)) {
            if ($relation->isHasOne()) {
                $value = $value->extract(... $extractArgs);
            } else {
                $value = array_map(fn($item) => $item->extract(... $extractArgs), $value);
            }
        }
        return $value;
    }

    /**
     * 初始化查询结果活动记录实例 (对新实例属性赋值)
     * @return ActiveRecord
     */
    public function markQueriedProperties(): static {
        $this->clearGetterValues()->createByQuery = true;
        $this->originalProperties = $this->extract();
        return $this;
    }

    /**
     * 取活动记录原始查询值
     * @return array
     */
    protected function getOriginalProperties(): array {
        return $this->originalProperties;
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
        return ActiveQuery::mappingToWhere(array_reduce(self::getPkProperties(), fn($m, $p) => array_merge($m, [$p->storeName => $p->dbValue($this->originalProperties[$p->name])]), []));
    }

    /**
     * 初始化活动记录静态属性 (本方法在ClassDecoratorManager中自动调用)
     * @param ReflectionClass $refClass
     * @return void
     * @throws ReflectionException
     */
    private static function initColumns(ReflectionClass $refClass): void {
        if ($refClass->isAbstract()) return;
        // Field初始化
        Structure::forEach(self::getProperties(), fn($p) =>
            ($attrs = $p->refProperty->getAttributes(static::$fieldClass)) && $p->setField($attrs[0]->newInstance()->setName($p->storeName)));
        // ActiveRelation初始化
        $fakeInstance = $refClass->newInstanceWithoutConstructor();
        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
            if ($method->getReturnType() instanceof ReflectionNamedType && $method->getReturnType()->getName() === ActiveRelation::class
                && ! $refClass->getParentClass()->hasMethod($method->name) && $relation = $method->invoke($fakeInstance))
                self::$relationMapping[static::class][$relation->name] = $relation;
    }

    /**
     * 根据关系名取关系实例
     * @param string $relationName
     * @return ActiveRelation|null
     */
    public static function getActiveRelation(string $relationName): ActiveRelation|null {
        return self::$relationMapping[static::class][$relationName] ?? null;
    }

    /**
     * 构建关联活动记录查询器
     * @param class-string<ActiveRecord> $foreignActiveRecordClass
     * @param array<string, string> $conditionMapping {[foreignColumn]: [primaryColumn]}
     * @param bool $one
     * @param string|null $viaRelationName
     * @param string|null $relationName
     * @return ActiveRelation
     * @throws ActiveException
     * @throws ModelException
     */
    private function buildRelation(string $foreignActiveRecordClass, array $conditionMapping, bool $one, string|null $viaRelationName, string $relationName = null): ActiveRelation {
        $relationName ??= preg_replace('/^get/', '', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function']);
        $relationName = static::toModelKey($relationName);
        $relation = $this->getActiveRelation($relationName);
        if (! $relation) {
            if (! is_subclass_of($foreignActiveRecordClass, self::class))
                throw (new ActiveException(ActiveException::RELATION_ACTIVE_RECORD_INVALID))->format($foreignActiveRecordClass, self::class);
            // 如果指定了依赖关系，则取该关系实例以便将其绑定到当前关系
            $viaRelation = $viaRelationName ? $this->callGetter($viaRelationName) : null;
            // 返回对象而不是查询结果是因为via、with等查询时需要取到关系条件，返回对象则可以从中取到条件
            $relation = new ActiveRelation($relationName, static::class, $foreignActiveRecordClass, $conditionMapping, $one, $viaRelation);
        }
        return $relation;
    }

    /**
     * 构建一对一关联数据查询器
     * @param class-string<ActiveRecord> $foreignActiveRecordClass
     * @param array<string, string> $conditionMapping {[foreignColumn]: [primaryColumn]}
     * @param string|null $viaRelationName
     * @param string|null $relationName
     * @return ActiveRelation
     * @throws ActiveException|ModelException
     */
    public function hasOne(string $foreignActiveRecordClass, array $conditionMapping, string $viaRelationName = null, string $relationName = null): ActiveRelation {
        return $this->buildRelation($foreignActiveRecordClass, $conditionMapping, true, $viaRelationName, $relationName);
    }

    /**
     * 构建一对多关联数据查询器
     * @param class-string<ActiveRecord> $foreignActiveRecordClass
     * @param array<string, string> $conditionMapping {[foreignColumn]: [primaryColumn]}
     * @param string|null $viaRelationName
     * @param string|null $relationName
     * @return ActiveRelation
     * @throws ActiveException|ModelException
     */
    public function hasMany(string $foreignActiveRecordClass, array $conditionMapping, string $viaRelationName = null, string $relationName = null): ActiveRelation {
        return $this->buildRelation($foreignActiveRecordClass, $conditionMapping, false, $viaRelationName, $relationName);
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
        static $nameMapping = [];
        $modelName = (static::$prefix ?? '') . static::class;
        ! key_exists($modelName, $nameMapping) && $nameMapping[$modelName] = static::toDbKey(preg_replace('/^.*\b(?=\w+$)/u', '', $modelName));
        return $nameMapping[$modelName];
    }

    /**
     * 取字段集
     * @return Property[]
     */
    public static function getFieldProperties(): array {
        if (! key_exists(static::class, self::$fieldProperties))
            self::$fieldProperties[static::class] = array_filter(array_values(static::getProperties()),
                fn($p) => isset($p->field) && $p->refProperty->getDeclaringClass()->name === static::class);
        return self::$fieldProperties[static::class];
    }

    /** @return Property[] */
    public static function getPkProperties(): array {
        ! key_exists(static::class, self::$pkProperties) && self::$pkProperties[static::class] = array_filter(self::getFieldProperties(), fn($p) => $p->field->isPrimaryKey());
        return self::$pkProperties[static::class];
    }

    /** @return string[] */
    public static function getPkNames(): array {
        return array_map(fn($p) => $p->name, self::getPkProperties());
    }

    /**
     * 取新的活动记录查询器实例
     * @return ActiveQuery
     */
    abstract public static function query(): ActiveQuery;

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

    /**
     * 将当前实体从数据库删除
     * @return int
     */
    abstract public function delete(): int;
}
