<?php
/**
 * Author: Drunk
 * Date: 2019/8/29 18:32
 */

namespace dce\model;

use ArrayAccess;
use dce\loader\Decorator;
use drunk\Char;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

abstract class Model implements ArrayAccess, Decorator {
    public const COVER_TYPE_UNSET = 'unset_default'; // todo 镜像升8.1后换成enum
    public const COVER_TYPE_REPLACE = 'replace_value';
    public const COVER_TYPE_IGNORE = 'ignore_existed';

    /** @var string 默认场景名 */
    public const SCENARIO_DEFAULT = 'default';

    /** @var string 字段类名, 用于实现注解字段 */
    protected static string $fieldClass;

    /** @var Property[][] 模型属性实例映射表 */
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
     * @param array|ArrayAccess $properties 待赋值属性键值表
     * @param string $coverType
     * @return $this
     */
    public function apply(array|ArrayAccess $properties, string $coverType = self::COVER_TYPE_REPLACE): static {
        $passedKeys = array_keys(is_array($properties)
            ? $properties = array_reduce(array_keys($properties), fn($ps, $k) => array_merge($ps, [static::toModelKey($k) => $properties[$k]]), [])
            : array_filter(get_object_vars($properties), fn($v) => $v !== null));

        if ($coverType === self::COVER_TYPE_IGNORE) {
            foreach ($passedKeys as $key) $this->$key ??= $properties[$key];
        } else {
            foreach ($passedKeys as $key) $this->$key = $properties[$key];
            if ($coverType === self::COVER_TYPE_UNSET) {
                foreach (static::getProperties() as $property) {
                    if ($property->refProperty->hasDefaultValue() && ! in_array($property->name, $passedKeys)) {
                        // 如果需要清空默认值，且属性有默认值也未被传入值覆盖，则清除掉
                        unset($this->{$property->name});
                    }
                }
            }
        }
        return $this;
    }

    /**
     * 提取模型属性键值表
     * @param bool $toDbKey
     * @param mixed $null
     * @return array
     */
    public function extract(bool $toDbKey = false, mixed $null = null): array {
        $mapping = [];
        foreach (static::getProperties() as $property) {
            if ($null !== $value = $property->getValue($this, $null)) {
                $mappingKey = $toDbKey ? static::toDbKey($property->name) : $property->name;
                $mapping[$mappingKey] = $value;
            }
        }
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
     * 初始化模型属性实例表 (本方法在ClassDecoratorManager中调用)
     * @param ReflectionClass $refClass
     */
    private static function initProperties(ReflectionClass $refClass): void {
        $staticClass = $refClass->getName();
        self::$scenarioRulesMapping[$staticClass] = [];
        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $refProperty) {
            if ($attributes = $refProperty->getAttributes(Property::class)) {
                $properties[$refProperty->name] = $propertyInstance = $attributes[0]->newInstance();
                $validatorInstances = [];
                foreach ($refProperty->getAttributes(Validator::class) as $ruleProperty) {
                    $validatorInstances[] = $ruleProperty->newInstance()->setProperty($propertyInstance);
                }
                $fieldInstance = isset($staticClass::$fieldClass) && ($fieldAttrs = $refProperty->getAttributes($staticClass::$fieldClass)) ? $fieldAttrs[0]->newInstance() : null;
                $propertyInstance->applyProperties($staticClass, $refProperty, $validatorInstances, $fieldInstance);

                // 按场景分组校验器
                foreach ($validatorInstances as $validator)
                    foreach ($validator->scenario as $scenario)
                        self::$scenarioRulesMapping[$staticClass][$scenario][] = $validator;
            }
        }
        // 将校验器按照类型分组
        array_walk(self::$scenarioRulesMapping[$staticClass], fn(&$validators) => $validators = Validator::validatorsClassify($validators));
        self::$properties[$staticClass] = $properties ?? [];
    }

    /**
     * 取实例类的模型属性实例表
     * @return Property[]
     */
    protected static function getProperties(): array {
        return self::$properties[static::class];
    }

    /**
     * 根据属性名取属性实例
     * @param string $name
     * @return Property|null
     */
    public function getProperty(string $name): Property|null {
        return self::getProperties()[$name] ?? null;
    }

    /**
     * 魔术方法, 处理getter
     * @param string $name
     * @return mixed
     * @throws ModelException
     */
    public function __get(string $name): mixed {
        // 若有缓存则直接返回
        if ($this->hasGetterValue($name)) {
            return $this->getGetterValue($name);
        }
        $returnValue = $this->callGetter($name, true);
        // 执行子级可能定义的路由方法
        $returnValue = $this->handleGetter($name, $returnValue);
        $this->setGetterValue($name, $returnValue);
        return $returnValue;
    }

    /**
     * 根据getter名调用对应方法
     * @param string $name
     * @param bool $throwable
     * @return mixed
     * @throws ModelException
     */
    public function callGetter(string $name, bool $throwable = false): mixed {
        $methodName = 'get' . Char::camelize($name, true);
        if ($throwable && ! method_exists($this, $methodName)) {
            throw (new ModelException(ModelException::GETTER_UNDEFINED))->format($name);
        }
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
     * @return $this
     */
    public function setGetterValue(string $name, mixed $value): static {
        $this->getterValues[$name] = $value;
        return $this;
    }

    /**
     * 取getter缓存值
     * @param string $name
     * @return mixed
     */
    protected function getGetterValue(string $name): mixed {
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

    public function offsetSet(mixed $offset, mixed $value) {
        $offset = static::toModelKey($offset);
        $this->$offset = $value;
    }

    public function offsetGet(mixed $offset) {
        $offset = static::toModelKey($offset);
        return $this->$offset;
    }

    public function offsetExists(mixed $offset): bool {
        $offset = static::toModelKey($offset);
        return isset($this->$offset);
    }

    public function offsetUnset(mixed $offset) {
        $offset = static::toModelKey($offset);
        unset($this->$offset);
    }

    /**
     * 以属性键值对实例化一个模型对象
     * @param array $properties
     * @param string $coverType
     * @param mixed ...$ctorArgs
     * @return $this
     */
    public static function from(array $properties, string $coverType = self::COVER_TYPE_UNSET, mixed ... $ctorArgs): static {
        $instance = new static(... $ctorArgs);
        $instance->apply($properties, $coverType);
        return $instance;
    }
}
