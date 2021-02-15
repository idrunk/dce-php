<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 10:05
 */

namespace dce\model\validator;

use dce\model\Model;
use drunk\Utility;
use ReflectionObject;
use ReflectionProperty;

abstract class ValidatorAbstract {
    /** @var string|int|float|false|null 待校验值 */
    protected string|int|float|null|false $value;

    /** @var string 规则通用错误模板 */
    protected string $error;

    /** @var string|null 待校验模型属性名 */
    protected string|null $modelPropertyName;

    /** @var string|null 待校验模型属性别名 */
    protected string|null $modelPropertyAlias;

    /** @var Model|null 待校验模型 */
    protected Model|null $model;

    /** @var ValidatorProperty[] 校验器属性实例集 */
    private array $properties = [];

    /** @var ValidatorException[] 当前异常集 */
    private array $errors;

    /** @var ReflectionProperty[] */
    private array $reflectProperties = [];

    private static array $instMapping = [];

    private function __construct(array $properties = []) {
        $reflectProperties = (new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PROTECTED);
        foreach ($reflectProperties as $property) {
            $propertyName = $property->getName();
            // 如果校验类有默认配置, 则将其赋值到property
            // 由Reflection的判断条件改成了直接检查属性，导致无法判断null，但似乎不影响
            if (isset($this->$propertyName)) {
                $this->setProperty($propertyName, $this->$propertyName, 'array' === $property->getType()->getName());
            }
            $this->reflectProperties[$propertyName] = $property;
        }
        if ($properties) {
            $this->setProperties($properties);
        }
    }

    /**
     * @param array $properties
     * @return $this
     * @throws ValidatorException
     */
    private function setProperties(array $properties): self {
        foreach ($this->reflectProperties as $propertyName => $property) {
            if (key_exists($propertyName, $properties)) {
                $this->setProperty($propertyName, $properties[$propertyName], 'array' === $property->getType()->getName());
            }
        }
        return $this;
    }

    protected function getValue(): string|int|float|null|false {
        return $this->value;
    }

    /**
     * @return bool
     */
    protected function valid(): bool {
        return true;
    }

    /**
     * 校验并返回校验处理过的值
     * @param string|int|float|false|null $value
     * @param string|null $propertyName
     * @param string|null $propertyAlias
     * @param Model|null $model
     * @return string|int|float|false|null
     * @throws ValidatorException
     */
    public function checkGetValue(
        string|int|float|null|false $value,
        string|null $propertyName = null,
        string|null $propertyAlias = null,
        Model|null $model = null
    ): string|int|float|null|false {
        $this->errors = [];
        $this->value = $value;
        $this->modelPropertyName = $propertyName;
        $this->modelPropertyAlias = $propertyAlias;
        $this->model = $model;

        $valid = $this->valid();
        $value = $this->getValue();
        if (! $valid) {
            $class = static::class;
            throw new ValidatorException("{$value} 无法通过 {$class} 校验");
        }
        return $value;
    }

    /**
     * @param string $name
     * @param string|int|float|array $value
     * @param bool $isArrayType
     * @return $this
     * @throws ValidatorException
     */
    private function setProperty(string $name, string|int|float|array $value, bool $isArrayType = false): self {
        $property = new ValidatorProperty($name, $value, $isArrayType, static::class);
        $this->properties[$name] = $property;
        $this->$name = $property->value;
        return $this;
    }

    /**
     * @param string $name
     * @param string|null $requiredError
     * @return ValidatorProperty|null
     */
    protected function getProperty(string $name, string|null $requiredError = null): ValidatorProperty|null {
        if (key_exists($name, $this->properties)) {
            return $this->properties[$name];
        } else {
            if ($requiredError) {
                $this->addError($this->getGeneralError(null, null) ?: $requiredError);
            }
            return null;
        }
    }

    /**
     * @param string|null $definedError
     * @param string|null $defaultError
     * @return string|null
     */
    protected function getGeneralError(string|null $definedError, string|null $defaultError): string|null {
        if ($definedError) {
            return $definedError;
        }
        $generalError = $this->getProperty('error');
        if ($generalError) {
            return $generalError->value;
        }
        return $defaultError;
    }

    /**
     * @param string $message
     * @param int $errorCode
     * @return $this
     */
    final protected function addError(string $message, int $errorCode = 0): self {
        $message = $this->makeError($message);
        $this->errors[] = new ValidatorException($message, $errorCode);
        return $this;
    }

    /**
     * @return ValidatorException|null
     */
    final protected function getError(): ValidatorException|null {
        return $this->errors[0] ?? null;
    }

    /**
     * @param string $errorTemplate
     * @return string
     */
    private function makeError(string $errorTemplate): string {
        $replacementMap = [
            '{{property}}' => $this->modelPropertyName,
            '{{label}}' => $this->modelPropertyAlias,
            '{{value}}' => Utility::printable($this->getValue()),
        ];
        $properties = $this->properties;
        foreach ($properties as $k=>$property) {
            $replacementMap["{{{$k}}}"] = (string) $property;
        }
        $searchMap = array_keys($replacementMap);
        $replacementMap = array_values($replacementMap);
        $error = str_ireplace($searchMap, $replacementMap, $errorTemplate);
        return $error;
    }

    final public static function inst(array $properties = []): static {
        $identity = static::class .':'. json_encode($properties);
        if (! key_exists($identity, self::$instMapping)) {
            self::$instMapping[$identity] = new static($properties);
        }
        return self::$instMapping[$identity];
    }
}
