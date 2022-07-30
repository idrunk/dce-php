<?php
/**
 * Author: Drunk
 * Date: 2019/8/29 18:32
 */

namespace dce\model;

use dce\base\CoverType;
use dce\base\ExtractType;
use dce\Dce;
use dce\loader\Decorator;
use drunk\Char;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

abstract class Model implements Decorator {
    /** @var string 默认场景名 */
    public const SCENARIO_DEFAULT = 'default';

    /** @var int 模型ID，用于扩展属性等 */
    public static int $modelId;

    /** @var bool 是否自动缓存（若开启，活动记录将自动缓存所有属性字段） */
    public static bool $cacheable = false;

    /** @var Property[][][] 模型属性实例映射表 */
    private static array $properties = [];

    /** @var Validator[][][] 模型属性校验规则映射表 */
    private static array $scenarioRulesMapping = [];

    /** @var array Getter属性键值映射表 */
    private array $getterValues = [];

    /**
     * Model constructor.
     * @param string $scenario 当前场景名
     */
    public function __construct(
        private string $scenario = self::SCENARIO_DEFAULT,
    ) {}

    /**
     * 应用模型属性值
     * @param array|self $properties 待赋值属性键值表
     * @param CoverType $coverType
     * @return $this
     */
    public function apply(array|self $properties, CoverType $coverType = CoverType::Replace): static {
        $passedKeys = array_keys(is_array($properties)
            ? $properties = array_reduce(array_keys($properties), fn($ps, $k) => array_merge($ps, [static::toModelKey($k) => $properties[$k]]), [])
            : array_filter(get_object_vars($properties), fn($v) => $v !== null));

        foreach ($passedKeys as $key) $this->setPropertyValue($key, $properties[$key], $coverType);

        if ($coverType === CoverType::Unset)
            foreach (static::getProperties() as $property)
                if ($property->refProperty->hasDefaultValue() && ! in_array($property->name, $passedKeys))
                    unset($this->{$property->name}); // 如果需要清空默认值，且属性有默认值也未被传入值覆盖，则清除掉

        return $this;
    }

    /**
     * 设置属性值
     * @param string $key
     * @param mixed $value
     * @param CoverType $coverType
     */
    public function setPropertyValue(string $key, mixed $value, CoverType $coverType = CoverType::Replace): void {
        $property = $this->getProperty($key);
        // 此处本不应设置getterValue，但为了ActiveRecord::setQueriedProperties及DbActiveQuery::select等能高效方便的将关系数据储存到新建对象中，方才自动处理
        $property ? $property->setValue($this, $value, $coverType === CoverType::Ignore) : $this->setGetterValue($key, $value, $coverType);
    }

    /**
     * 提取模型属性键值表
     * @param ExtractType $type {true: toDbKey and toStorable, null: toDbKey, false: return as is key and value}
     * @param mixed $null
     * @return array
     */
    public function extract(ExtractType $type = ExtractType::KeepKey, mixed $null = null): array {
        $mapping = [];
        // 若为储存，则仅留纯Property（排除ExtProperty）
        $filterFn = match ($type) {
            ExtractType::DbSave => fn($p) => ! $p->isExtend(),
            ExtractType::ExtSave => fn($p) => $p->isExtend(),
            default => false,
        };
        foreach (($filterFn ? array_filter(static::getProperties(), $filterFn) : static::getProperties()) as $property) {
            if ($null !== $value = $property->getValue($this, $null, in_array($type, [ExtractType::DbSave, ExtractType::Serialize]))) {
                $mappingKey = ExtractType::KeepKey === $type ? $property->name : static::toDbKey($property->name);
                $mapping[$mappingKey] = $value;
            }
        }
        if (false === $filterFn)
            foreach (array_keys($this->getterValues) as $k) $mapping[ExtractType::KeepKey === $type ? $k : static::toDbKey($k)] = $this->getGetterValue($k, [$type, $null]);
        return $mapping;
    }

    /**
     * 将数据库数据转为Model对象时默认以小驼峰方式命名属性名
     * @param string $key
     * @return string
     */
    public static function toModelKey(string $key): string {
        return Char::camelize($key);
    }

    /**
     * 将Model对象转为数据库数据时默认以蛇底方式命名字段名
     * @param string $key
     * @return string
     */
    public static function toDbKey(string $key): string {
        return Char::snakelike($key);
    }

    /**
     * 设置当前场景名
     * @param string $scenario
     * @return $this
     */
    public function setScenario(string $scenario): static {
        $this->scenario = $scenario;
        return $this;
    }

    /**
     * 校验当前模型属性值
     * @return $this
     * @throws Throwable
     * @throws validator\ValidatorException
     */
    public function valid(): static {
        Validator::valid($this, self::$scenarioRulesMapping[static::class][$this->scenario] ?? []);
        return $this;
    }

    /**
     * 根据模型属性校验器配置修正并校验数据
     * @param array|scalar|Model $value 待校验数据或模型
     * @param string $prop 对应模型属性名
     * @param mixed|Validator::RULE_* $rule 适用规则，默认全规则
     * @param mixed|static::SCENARIO_* $scenario 适用场景，默认全场景
     * @throws Throwable
     * @throws validator\ValidatorException
     */
    public static function correct(array|string|int|float|Model|bool|null & $value, string $prop, string|null $rule = null, string|null $scenario = null): void {
        $prop = static::toModelKey($prop);
        $classValidators = self::$scenarioRulesMapping[static::class];
        $scenario && key_exists($scenario, $classValidators) && $classValidators = [$scenario => $classValidators[$scenario]];
        $validators = array_reduce($classValidators, function($validators, $currentValidators) use($prop, $rule) {
            foreach ($currentValidators as $type => $typeValidators) {
                ! key_exists($type, $validators) && $validators[$type] = [];
                // 仅取指定属性且或指定规则的校验器集
                $typeValidators = array_filter($typeValidators, fn(Validator $validator) => $prop === $validator->property->name && (! $rule || $validator->rule === $rule));
                array_push($validators[$type], ... $typeValidators);
            }
            return $validators;
        }, []);
        is_array($value) ? array_walk($value, fn(&$v) => Validator::valid($v, $validators)) : Validator::valid($value, $validators);
    }

    /**
     * 初始化模型属性实例表 (本方法在ClassDecoratorManager中自动调用)
     * @param ReflectionClass $refClass
     * @throws validator\ValidatorException
     */
    private static function initProperties(ReflectionClass $refClass): void {
        $staticClass = $refClass->getName();
        self::$scenarioRulesMapping[$staticClass] = [];
        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $refProperty) {
            if ($attributes = $refProperty->getAttributes(Property::class, ReflectionAttribute::IS_INSTANCEOF)) {
                $properties[$refProperty->name] = $propertyInstance = $attributes[0]->newInstance();
                $validatorInstances = [];
                foreach ($refProperty->getAttributes(Validator::class) as $ruleProperty)
                    $validatorInstances[] = $ruleProperty->newInstance()->setProperty($propertyInstance);
                $propertyInstance->applyProperties($refProperty, $validatorInstances, $staticClass);

                // 按场景分组校验器
                foreach ($validatorInstances as $validator)
                    foreach ($validator->scenario as $scenario)
                        self::$scenarioRulesMapping[$staticClass][$scenario][] = $validator;
            }
        }
        // 将校验器按照类型分组
        array_walk(self::$scenarioRulesMapping[$staticClass], fn(&$validators) => $validators = Validator::validatorsClassify($validators));
        self::$properties[$staticClass]['nameIndex'] = $properties ?? [];
        self::$properties[$staticClass]['idIndex'] = array_reduce(self::$properties[$staticClass]['nameIndex'], fn($m, $p) => $m + ($p?->id ? [$p->id => $p] : []), []);
    }

    /**
     * 取实例类的模型属性实例表
     * @return Property[]
     */
    protected static function getProperties(bool $nameIndexed = true): array {
        return self::$properties[static::class][$nameIndexed ? 'nameIndex' : 'idIndex'];
    }

    /** @return Property[] */
    public static function getExtProperties(): array {
        $properties = Dce::$cache->var->get(['ext_properties', static::class]);
        ! $properties && Dce::$cache->var->set(['ext_properties', static::class],
            $properties = array_filter(self::getProperties(false), fn($p) => $p->isExtend()));
        return $properties;
    }

    /** @return Property[] */
    protected static function getModifyRecordProperties(): array {
        $properties = Dce::$cache->var->get(['modify_record_properties', static::class]);
        ! $properties && Dce::$cache->var->set(['modify_record_properties', static::class],
            $properties = array_filter(self::getProperties(false), fn($p) => $p->isModifyRecord()));
        return $properties;
    }

    /** @return Property[] */
    protected static function getCacheProperties(): array {
        $properties = Dce::$cache->var->get(['cache_properties', static::class]);
        ! $properties && Dce::$cache->var->set(['cache_properties', static::class],
            $properties = array_filter(self::getProperties(false), fn($p) => $p->isNeedCache(static::class)));
        return $properties;
    }

    /**
     * 根据属性名取属性实例
     * @param string $name
     * @param callable|null $thrownSupplier
     * @return Property|null
     */
    public static function getProperty(string $name, callable $thrownSupplier = null): Property|null {
        return self::getProperties()[$name] ?? ($thrownSupplier ? call_user_func($thrownSupplier) : null);
    }

    public static function getPropertyById(int $id, callable $thrownSupplier = null): Property|null {
        return self::getProperties(false)[$id] ?? ($thrownSupplier ? call_user_func($thrownSupplier) : null);
    }

    /**
     * 魔术方法, 处理getter
     * @param string $name
     * @return mixed
     * @throws ModelException
     */
    public function __get(string $name): mixed {
        // 若有缓存则直接返回
        if ($this->hasGetterValue($name))
            return $this->getGetterValue($name);
        $returnValue = $this->callGetter($name, true);
        // 执行子级可能定义的路由方法
        $returnValue = $this->handleGetter($name, $returnValue);
        $this->setGetterValue($name, $returnValue);
        return $returnValue;
    }

    /** 魔术方法，判断是否存在getter属性 */
    public function __isset(string $name): bool {
        return isset($this->getterValues[$name]);
    }

    /** 魔术方法，删除getter属性 */
    public function __unset(string $name): void {
        unset($this->getterValues[$name]);
    }

    /**
     * 根据getter名调用对应方法
     * @param string $name
     * @param bool $throwable
     * @return mixed
     * @throws ModelException
     */
    protected function callGetter(string $name, bool $throwable = false): mixed {
        $methodName = 'get' . Char::camelize($name, true);
        if ($throwable && ! method_exists($this, $methodName))
            throw (new ModelException(ModelException::GETTER_UNDEFINED))->format($name);
        return $this->$methodName();
    }

    /**
     * 处理getter方法返回值 (主用于实现活动记录取关联数据)
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    protected function handleGetter(string $name, mixed $value): mixed {
        return $value;
    }

    /**
     * 缓存getter值
     * @param string $name
     * @param mixed $value
     * @param CoverType $coverType
     */
    protected function setGetterValue(string $name, mixed $value, CoverType $coverType = CoverType::Replace): void {
        ($coverType !== CoverType::Ignore || ! $this->hasGetterValue($name)) && $this->getterValues[$name] = $value;
    }

    /**
     * 取getter缓存值
     * @param string $name
     * @param array $extractArgs
     * @return mixed
     */
    protected function getGetterValue(string $name, array $extractArgs = []): mixed {
        return $this->getterValues[$name] ?? null;
    }

    /**
     * 判断相应getter是否已有缓存值
     * @param string $name
     * @return bool
     */
    protected function hasGetterValue(string $name): bool {
        return key_exists($name, $this->getterValues);
    }

    /**
     * 清除getter缓存值
     * @return static
     */
    protected function clearGetterValues(): static {
        $this->getterValues = [];
        return $this;
    }

    /**
     * 以属性键值对实例化一个模型对象
     * @param array $properties
     * @param CoverType $coverType
     * @param mixed ...$ctorArgs
     * @return $this
     */
    public static function from(array $properties, CoverType $coverType = CoverType::Replace, mixed ... $ctorArgs): static {
        return (new static(... $ctorArgs))->apply($properties, $coverType);
    }
}
