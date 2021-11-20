<?php
/**
 * Author: Drunk
 * Date: 2019/8/20 16:20
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;

class GroupSchema extends SchemaAbstract {
    public function setGroup(string|array|RawBuilder $columns, bool $isAutoRaw) {
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        } else if (! is_array($columns)) {
            $columns = [$columns];
        }
        foreach ($columns as $column) {
            if ($isAutoRaw && is_string($column)) {
                $column = new RawBuilder($column, false);
            }
            if ($column instanceof RawBuilder) {
                $this->pushCondition($column);
                $this->mergeParams($column->getParams());
            } else if (is_string($column) && $column = self::tableWrap($column)) {
                $this->pushCondition($column);
            } else {
                throw (new QueryException(QueryException::GROUP_COLUMN_INVALID))->format(self::printable($column));
            }
        }
    }

    public function __toString(): string {
        return implode(',', $this->getConditions());
    }
}
