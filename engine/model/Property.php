<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/1/25 15:58
 */

namespace dce\model;

use Attribute;
use BackedEnum;
use dce\base\PropertyFlag;
use dce\base\StorableType;
use dce\db\entity\Field;
use drunk\Structure;
use ReflectionProperty;
use ReflectionUnionType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Property {
    readonly public ReflectionProperty $refProperty;

    readonly public string $name;

    readonly public string $storageName;

    /** @var Validator[] */
    readonly public array $validators;

    readonly public Field $field;

    readonly public StorableType $valueType;

    /** @var class-string<BackedEnum> */
    readonly public string $valueClass;

    /**
     * Property constructor.
     * @param string|null $alias 属性别名
     */
    public function __construct(
        public string|null $alias = null,
        /**
         * <pre>
         * 一个模型中的属性ID需要遵循以下标准：
         * 1. 同模型中所有属性ID不能重复
         * 2. 属性删除后，新增的属性不能复用被删除的属性ID
         * 3. 以上述第二点为基础，建议属性ID从1开始递增，所有新定义属性都在最后的ID加1
         * 4. 属性重命名无需更新ID
         * </pre>
         * @var int|null
         */
        readonly public int|null $id = null,
        /**
         * 复合位标志，有效标志可见于 \dce\base\PropertyFlag
         * @var list<PropertyFlag>
         */
        readonly public array $bitFlag = [],
    ) {}

    /**
     * 给Property对象绑定各种属性
     * @param ReflectionProperty $refProperty
     * @param array $validators
     * @param class-string<Model> $modelClass
     * @return $this
     */
    public function applyProperties(ReflectionProperty $refProperty, array $validators, string $modelClass): self {
        $this->refProperty = $refProperty;
        $this->name = $refProperty->name;
        $this->storeName = $modelClass::toDbKey($this->name);
        $this->validators = $validators;
        ! $this->alias && $this->alias = $this->name;
        if ($type = $refProperty->getType()) { // 如果指定了属性类型，则记录之
            $type instanceof ReflectionUnionType && $type = Structure::arraySearchItem(fn($t) => $t->isBuiltin(), $type->getTypes()) ?: $type->getTypes()[0];
            $this->valueClass = $type->getName();
            if ($type->isBuiltin()) {
                $this->valueType = $this->valueClass === 'array' ? StorableType::Array : StorableType::Scalar;
            } else if (class_exists($this->valueClass)) {
                $this->valueType = is_subclass_of($this->valueClass, BackedEnum::class) ? StorableType::BackedEnum : StorableType::Serializable;
            } else {
                $this->valueType = StorableType::Unable;
            }
        } else {
            $this->valueType = StorableType::Scalar;
        }
        return $this;
    }

    public function setField(Field $field): void {
        $this->field = $field;
    }

    /**
     * 属性是否有初始值
     * @param Model $model
     * @return bool
     */
    public function isInitialized(Model $model): bool {
        return $this->refProperty->isInitialized($model);
    }

    /**
     * 是否扩展字段
     * @return bool
     */
    public function isExtend(): bool {
        return in_array(PropertyFlag::ExtendColumn, $this->bitFlag);
    }

    /**
     * 是否需记录修改记录字段
     * @return bool
     */
    public function isModifyRecord(): bool {
        return in_array(PropertyFlag::ModifyRecord, $this->bitFlag);
    }

    /**
     * 是否需缓存
     * @param class-string<Model> $modelClass
     * @return bool
     */
    public function isNeedCache(string $modelClass): bool {
        return in_array(PropertyFlag::Cache, $this->bitFlag) || $modelClass::$cacheable && ! in_array(PropertyFlag::NoCache, $this->bitFlag);
    }

    /**
     * 将属性值设置到模型
     * @param Model $model
     * @param mixed $value
     * @param bool $ignoreExists
     */
    public function setValue(Model $model, mixed $value, bool $ignoreExists): void {
        if (! $ignoreExists || ! isset($model->{$this->name}))
            $model->{$this->name} = match ($this->valueType) {
                StorableType::Array => is_string($value) ? json_decode($value, true) : $value,
                StorableType::BackedEnum => is_scalar($value) ? $this->valueClass::from($value) : $value,
                StorableType::Serializable => is_string($value) ? unserialize($value) : $value,
                default => $value,
            };
    }

    /**
     * 取模型属性值, false表示属性未初始化
     * @param Model $model
     * @param mixed|null $default
     * @param bool $toStorable 是否转换为可储存串
     * @return mixed
     */
    public function getValue(Model $model, mixed $default = null, bool $toStorable = false): mixed {
        if ($this->isInitialized($model)) {
            $default = $model->{$this->name};
            $toStorable && $default = $this->dbValue($default);
        }
        return $default;
    }

    public function dbValue(mixed $value): string|int|null {
        return match ($this->valueType) {
            StorableType::Array => json_encode($value, JSON_UNESCAPED_UNICODE),
            StorableType::BackedEnum => $value->value,
            StorableType::Serializable => serialize($value),
            default => $value,
        };
    }
}