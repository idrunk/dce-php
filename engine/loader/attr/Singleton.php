<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/8/20 23:50
 */

namespace dce\loader\attr;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Singleton extends SingMatrix {
    protected static function genInstanceId(string $class, array $args): string {
        return $class;
    }
}