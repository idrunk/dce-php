<?php
/**
 * Author: Drunk
 * Date: 2020-09-27 11:42
 */

namespace dce\config;

use ArrayAccess;
use ArrayObject;
use drunk\Char;
use drunk\Structure;
use ReflectionObject;
use SplFixedArray;

class Config implements ArrayAccess {
    private static array $instMapping = [];

    private array $dynamics = [];

    /**
     * ConfigMatrix constructor.
     * @param array|ArrayAccess $config
     * @throws ConfigException
     * @throws \ReflectionException
     */
    function __construct(array|ArrayAccess $config = []) {
        $this->extend($config);
    }

    /**
     * 取一个单例对象
     * @param array|ArrayAccess $config
     * @return static
     * @throws ConfigException
     * @throws \ReflectionException
     */
    public static function inst(array|ArrayAccess $config = []): static {
        if (! key_exists(static::class, self::$instMapping)) {
            self::$instMapping[static::class] = new static($config);
        }
        return self::$instMapping[static::class];
    }

    /**
     * 取全部配置
     * @return array
     */
    public function arrayify(): array {
        $properties = get_object_vars($this);
        unset($properties['dynamics']);
        return $properties + $this->dynamics;
    }

    /**
     * 取配置
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed {
        $property = Char::camelize($key);
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        Structure::arraySplitKey($key, $key, $keyArray); // 拆分键为主键及数组下标数组
        if (! isset($this->dynamics[$key])) {
            return null; // 若无该配置, 则返回null
        }
        $value = $this->dynamics[$key];
        if (empty($keyArray)) {
            return $value; // 若非取子元素, 则直接返回整个配置
        }
        return Structure::arrayIndexGet($value, $keyArray);
    }

    /**
     * 配置设置
     * @param string $key
     * @param mixed $value
     * @return $this
     * @throws ConfigException
     * @throws \ReflectionException
     */
    public function set(string $key, mixed $value): self {
        $property = Char::camelize($key);
        if (! $this->setProperty($property, $value)) {
            Structure::arraySplitKey($key, $key, $keyArray);
            if (empty($keyArray)) {
                $this->dynamics[$key] = $value; // 若非设置子元素, 则直接覆盖配置
                return $this;
            }
            if (! array_key_exists($key, $this->dynamics)) {
                $this->dynamics[$key] = []; // 若未定义配置, 则初始化为空数组
            }
            $valueRoot = Structure::arrayAssign($this->dynamics[$key], $keyArray, $value);
            if (! $valueRoot) {
                throw new ConfigException('配置迭代赋值出错');
            }
            $this->dynamics[$key] = $valueRoot;
        }
        return $this;
    }

    /**
     * 设置对象属性
     * @param string $key
     * @param mixed $value
     * @return bool
     * @throws ConfigException
     */
    private function setProperty(string $key, mixed $value): bool {
        $refObject = new ReflectionObject($this);
        if ($refObject->hasProperty($key)) {
            $type = $refObject->getProperty($key)->getType();
            $typeName = $type->getName();
            $typeIsArray = 'array' === $typeName;
            $typeIsConfigLib = ! $typeIsArray && is_subclass_of($typeName, ConfigLibInterface::class);
            $typeIsConfig = ! $typeIsConfigLib && is_subclass_of($typeName, Config::class);
            if (null !== $value && $typeIsArray || $typeIsConfig || $typeIsConfigLib) {
                $valueIsArray = is_array($value);
                // SplFixedArray或ArrayObject型配置合并或扩展配置时, 属性会被直接替换而不是合并
                $valueObjectArray = $valueIsArray ? false : match (true) {
                    $value instanceof SplFixedArray => $value->toArray(),
                    $value instanceof ArrayObject => $value->getArrayCopy(),
                    default => false,
                };
                if (false !== $valueObjectArray) {
                    $value = $valueObjectArray;
                } else if (! $valueIsArray && ! $value instanceof ArrayAccess) {
                    throw new ConfigException(sprintf("配置 %s 非法", $key));
                }
                if ($typeIsConfig) {
                    // 当配置已实例化过且新的配置为数组时, 则为扩展配置, 否则为替换配置
                    $value = isset($this->$key) && $valueIsArray ? $this->$key->extend($value): new $typeName($value);
                } else if ($typeIsConfigLib) {
                    // 配置库型配置皆视为替换配置
                    $value = $typeName::load($value);
                } else if ($valueIsArray) {
                    // 如果新传配置为数组, 则为扩展数组
                    $value = Structure::arrayMerge($this->$key ?? [], $value);
                }
            }
            $this->$key = $value;
            return true;
        }
        return false;
    }

    /**
     * 混入配置项
     * @param array|ArrayAccess $config
     * @return $this
     * @throws ConfigException
     * @throws \ReflectionException
     */
    public function extend(array|ArrayAccess $config): self {
        foreach ($config as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * 删除配置
     * @param string $key
     * @return self
     */
    public function del(string $key): self {
        Structure::arraySplitKey($key, $key, $keyArray);
        if (! array_key_exists($key, $this->dynamics)) {
            return $this;
        }
        if (empty($keyArray)) {
            unset($this->dynamics[$key]);
        } else {
            Structure::arrayIndexDelete($this->dynamics[$key], $keyArray);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function empty(): self {
        $this->dynamics = [];
        return $this;
    }

    /**
     * 取配置值
     * @param string $key
     * @return mixed
     */
    public function __get(string $key) {
        return $this->get($key);
    }

    /**
     * 配置设置
     * @param string $key
     * @param mixed $value
     * @throws ConfigException
     * @throws \ReflectionException
     */
    public function __set(string $key, $value) {
        $this->set($key, $value);
    }

    /** @inheritDoc */
    public function offsetExists($offset) {
        return key_exists($offset, $this->arrayify());
    }

    /** @inheritDoc */
    public function offsetGet($offset) {
        return $this->get($offset);
    }

    /** @inheritDoc */
    public function offsetSet($offset, $value) {
        $this->set($offset, $value);
    }

    /** @inheritDoc */
    public function offsetUnset($offset) {
        $this->del($offset);
    }
}