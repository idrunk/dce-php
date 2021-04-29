<?php
/**
 * Author: Drunk
 * Date: 2019/8/21 19:03
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\SchemaAbstract;

class DeleteSchema extends SchemaAbstract {
    public function __construct(string|array|null $tableNames) {
        if (is_string($tableNames)) {
            $tableNames = explode(',', $tableNames);
        }
        if (! empty($tableNames)) {
            foreach ($tableNames as $tableName) {
                if (! is_string($tableName) || ! $table = self::tableWrap($tableName)) {
                    throw new QueryException(QueryException::TABLE_NAME_INVALID);
                }
                $this->pushCondition($table);
            }
        }
    }

    public function __toString(): string {
        return implode(',', $this->getConditions());
    }
}
