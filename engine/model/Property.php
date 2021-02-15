<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/1/25 15:58
 */

namespace dce\model;

use Attribute;
use dce\db\entity\Field;
use ReflectionProperty;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Property {
    public ReflectionProperty $refProperty;

    public string $name;

    /** @var Validator[] */
    public array $validators = [];

    public Field $field;

    /**
     * Property constructor.
     * @param string|null $alias 属性别名
     */
    public function __construct(
        public string|null $alias = null,
    ) {}

    public function applyProperties(string $modelClass, ReflectionProperty $refProperty, array $validators, Field|null $field): self {
        $this->refProperty = $refProperty;
        $this->name = $refProperty->name;
        $this->validators = $validators;
        if (! $this->alias) {
            $this->alias = $this->name;
        }
        if ($field) {
            $field->setName($modelClass::toDbKey($this->name));
            $this->field = $field;
        }
        return $this;
    }

    /**
     * 取模型属性值, false表示属性未初始化
     * @param Model $model
     * @return string|int|float|false|null
     */
    public function getValue(Model $model): string|int|float|null|false {
        return $this->refProperty->isInitialized($model) ? $model->{$this->name} : false;
    }
}