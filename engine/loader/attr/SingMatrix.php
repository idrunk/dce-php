<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/10/18 21:37
 */

namespace dce\loader\attr;

abstract class SingMatrix {
    protected static $mapping = [];

    public function __construct(mixed ... $args) {}

    /**
     * @template T
     * @param class-string<T>|callable $class
     * @param mixed ...$args
     * @return T
     */
    public static function gen(string|callable $class, mixed ... $args): mixed {
        $isCallable = is_callable($class);
        $id = static::genInstanceId($isCallable ? array_shift($args) : $class, $args);
        return key_exists($id, static::$mapping) ? static::$mapping[$id] : static::logInstance($id, $isCallable ? call_user_func($class) : self::new($class, ... $args));
    }

    /**
     * @template T
     * @param string $id
     * @param T $instance
     * @return T
     */
    public static function logInstance(string $id, mixed $instance): mixed {
        return static::$mapping[$id] = $instance;
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @param mixed ...$args
     * @return T|string
     */
    public static function generated(string $class, mixed ... $args): mixed {
        $id = static::genInstanceId($class, $args);
        return key_exists($id, static::$mapping) ? static::$mapping[$id] : $id;
    }

    public static function destroy(string $class, mixed ... $args): void {
        unset(static::$mapping[static::genInstanceId($class, $args)]);
    }

    protected abstract static function genInstanceId(string $class, array $args): string;

    /**
     * @template T
     * @param class-string<T> $class
     * @param mixed ...$args
     * @return T
     */
    public static function new(string $class, mixed ... $args): mixed {
        return new $class(... $args);
    }
}