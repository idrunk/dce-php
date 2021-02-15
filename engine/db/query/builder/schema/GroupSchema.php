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
            } else if (is_string($column) && $column = self::tableWrap($column)) {
                $this->pushCondition($column);
            } else {
                throw new QueryException("分组字段\"".self::printable($column)."\"无效", 1);
            }
        }
    }

    public function __toString(): string {
        return implode(',', $this->getConditions());
    }
}
