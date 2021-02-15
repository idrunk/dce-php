<?php
/**
 * Author: Drunk
 * Date: 2019/8/28 10:39
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class UnionSchema extends SchemaAbstract {
    public function addUnion(SelectStatement|RawBuilder $statement, bool $isAll) {
        $this->pushCondition([$statement, $isAll]);
        $this->mergeParams($statement->getParams());
        $this->logHasSubQuery(true);
    }

    public function __toString(): string {
        $conditions = [];
        foreach ($this->getConditions() as [$statement, $isAll]) {
            $conditions[] = ($isAll ? 'UNION ALL ' : 'UNION ' ) . sprintf($statement instanceof  RawBuilder ? '%s' : '(%s)', $statement);
        }
        return implode(' ', $conditions);
    }
}
