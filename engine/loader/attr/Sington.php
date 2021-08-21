<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/8/21 2:31
 */

namespace dce\loader\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Sington {
    public function __construct(mixed ... $args) {}

    /**
     * @template T
     * @param class-string<T> $class
     * @param mixed ...$args
     * @return T
     */
    public static function gen(string $class, mixed ... $args): mixed {
        static $mapping = [];
        $id = md5($class . (count($args) <= 1 && is_scalar($arg1 = $args[0] ?? '') ? $arg1 : json_encode($args)));
        ! key_exists($id, $mapping) && $mapping[$id] = new $class(... $args);
        return $mapping[$id];
    }

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