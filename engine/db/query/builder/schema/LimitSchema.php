<?php
/**
 * Author: Drunk
 * Date: 2019/8/20 16:53
 */

namespace dce\db\query\builder\schema;

use dce\db\query\builder\SchemaAbstract;

class LimitSchema extends SchemaAbstract {
    public function setLimit(int $limit, int $offset) {
        if ($limit > 0) {
            $this->pushCondition($limit, false);
            if ($offset > 0) {
                $this->pushCondition($offset, true);
            }
        }
    }

    public function __toString(): string {
        return implode(',', $this->getConditions());
    }
}
