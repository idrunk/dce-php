<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/8/21 2:31
 */

namespace dce\loader\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Sington extends SingMatrix {
    protected static function genInstanceId(string $class, array $args): string {
        return count($args) <= 1 && is_scalar($arg1 = $args[0] ?? '') ? $class . $arg1 : md5($class . json_encode($args));
    }
}