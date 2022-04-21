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
use drunk\Structure;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;

abstract class ActiveRecord extends Model {
    /** @var class-string<Field> */
    protected static string $fieldClass;

    /** @var array<string, Field[]> 活动记录字段集 */
    private static array $fields = [];

    /** @var array<string, Field[]> 活动记录主键集 */
    private static array $pkFields = [];

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
     * @return array
     */
    public function getPkValues(): array {
        return array_reduce(static::getPkNames(), fn($carry, $pk) => $carry + [$pk => $this->$pk], []);
    }

    /**
     * 处理Getter值 (取关联数据)
     * @param string $name
     * @param mixed $value
     * @return ActiveRecord|array|string|int|float|false|null
     */
    protected function handleGetter(string $name, mixed $value): ActiveRecord|array|string|int|float|false|null {
        $value instanceof ActiveRelation && $value = $value->screen($this);
        return $value;
    }

    /**
     * 初始化查询结果活动记录实例 (对新实例属性赋值)
     * @param array $properties
     * @return ActiveRecord
     */
    public function setQueriedProperties(array $properties): static {
        $this->clearGetterValues()->createByQuery = true;
        static::apply($properties, CoverType::Unset);
        $this->originalProperties = $this->extract(true, false);
        return $this;
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
        return self::mappingToWhere($this->originalProperties);
    }

    /**
     * 将模型属性键值表转查询条件
     * @param array $mapping
     * @return array
     */
    protected static function mappingToWhere(array $mapping): array {
        return array_map(fn($kv) => [$kv[0], '=', $kv[1]], Structure::arrayEntries($mapping));
    }

    /**
     * 保存数据 (插入或更新数据库)
     * @return int|string
     */
    public function save(): int|string {
        return $this->createByQuery ? $this->update() : $this->insert();
    }

    /**
     * 删除数据库记录
     * @return int
     * @throws ActiveException
     */
    public function delete(): int {
        ! $this->createByQuery && throw new ActiveException(ActiveException::CANNOT_DELETE_BEFORE_SAVE);
        $where = $this->genPropertyConditions();
        return static::query()->where($where)->delete();
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
            ($attrs = $p->refProperty->getAttributes(static::$fieldClass)) && $p->setField($attrs[0]->newInstance()->setName(static::toDbKey($p->name))));
        // ActiveRelation初始化
        $fakeInstance = $refClass->newInstanceWithoutConstructor();
        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
            if ($method->getReturnType() instanceof ReflectionNamedType && $method->getReturnType()->getName() === ActiveRelation::class && ! $refClass->getParentClass()->hasMethod($method->name))
                self::$relationMapping[static::class][($relation = $method->invoke($fakeInstance))->getName()] = $relation;
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
        if (! key_exists(static::class, $nameMapping))
            $nameMapping[static::class] = static::toDbKey(preg_replace('/^.*\b(?=\w+$)/u', '', static::class));
        return $nameMapping[static::class];
    }

    /**
     * 取字段集
     * @return Field[]
     */
    public static function getFields(): array {
        if (! key_exists(static::class, self::$fields))
            self::$fields[static::class] = array_column(array_filter(static::getProperties(), fn($p) => isset($p->field)), 'field');
        return self::$fields[static::class];
    }

    /** @return Field[] */
    public static function getPks(): array {
        ! key_exists(static::class, self::$pkFields) && self::$pkFields[static::class] = array_filter(self::getFields(), fn($f) => $f->isPrimaryKey());
        return self::$pkFields[static::class];
    }

    /** @return string[] */
    public static function getPkNames(): array {
        return array_map(fn($f) => $f->getName(), self::getPks());
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
     * @param array $columns 指定需更新的属性字段而不更新全部
     * @return int
     */
    abstract public function update(array $columns = []): int;
}
