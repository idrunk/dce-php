<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/1/25 15:58
 */

namespace dce\model;

use Attribute;
use BackedEnum;
use dce\base\StorableType;
use dce\db\entity\Field;
use drunk\Structure;
use ReflectionProperty;
use ReflectionUnionType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Property {
    readonly public ReflectionProperty $refProperty;

    readonly public string $name;

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
    ) {}

    /**
     * 给Property对象绑定各种属性
     * @param ReflectionProperty $refProperty
     * @param array $validators
     * @return $this
     */
    public function applyProperties(ReflectionProperty $refProperty, array $validators): self {
        $this->refProperty = $refProperty;
        $this->name = $refProperty->name;
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
            $toStorable && $default = match ($this->valueType) {
                StorableType::Array => json_encode($default, JSON_UNESCAPED_UNICODE),
                StorableType::BackedEnum => $default->value,
                StorableType::Serializable => serialize($default),
                default => $default,
            };
        }
        return $default;
    }
}