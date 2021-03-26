<?php
/**
 * Author: Drunk
 * Date: 2019/8/20 16:53
 */

namespace dce\db\query\builder\schema;

use dce\db\query\builder\SchemaAbstract;

class LimitSchema extends SchemaAbstract {
    public function setLimit(int $limit, int $offset): void {
        if ($limit > 0) {
            $this->pushCondition($limit, false);
            if ($offset > 0) {
                $this->pushCondition($offset, true);
            }
        } else {
            // 否则清空Limit条件
            $this->pushCondition(false);
        }
    }

    public function __toString(): string {
        return implode(',', $this->getConditions());
    }
}
