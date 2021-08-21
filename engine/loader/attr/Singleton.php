<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/8/20 23:50
 */

namespace dce\loader\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Singleton {
    public function __construct(mixed ... $args) {}

    /**
     * @template T
     * @param class-string<T> $class
     * @param mixed ...$args
     * @return T
     */
    public static function gen(string $class, mixed ... $args): mixed {
        static $mapping = [];
        ! key_exists($class, $mapping) && $mapping[$class] = new $class(... $args);
        return $mapping[$class];
    }
}