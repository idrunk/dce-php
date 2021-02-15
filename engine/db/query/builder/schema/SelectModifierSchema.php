<?php
/**
 * Author: Drunk
 * Date: 2019/10/9 17:52
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\SchemaAbstract;

class SelectModifierSchema extends SchemaAbstract {
    private string $modifier;

    private const MODIFIER_SET = ['ALL', 'DISTINCT', 'HIGH_PRIORITY', 'STRAIGHT_JOIN', 'SQL_BIG_RESULT', 'SQL_SMALL_RESULT', 'SQL_BUFFER_RESULT', 'SQL_CACHE', 'SQL_NO_CACHE'];

    public function __construct(string $modifier) {
        $upperModifier = strtoupper(trim($modifier));
        if ($upperModifier) {
            if (! in_array($upperModifier, self::MODIFIER_SET)) {
                throw new QueryException("无效的select修饰符{$modifier}");
            }
            $this->pushCondition($upperModifier);
        }
        $this->modifier = $upperModifier;
    }

    public function __toString(): string {
        return $this->modifier;
    }
}
