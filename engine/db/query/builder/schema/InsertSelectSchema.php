<?php
/**
 * Author: Drunk
 * Date: 2019/8/21 16:15
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class InsertSelectSchema extends SchemaAbstract {
    private $columns = [];

    public function __construct(SelectStatement|RawBuilder $selectStatement, string|array $columns) {
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }
        if (is_array($columns)) {
            foreach ($columns as $column) {
                if (! is_string($column) || ! $columnsName = self::tableWrap($column)) {
                    throw (new QueryException(QueryException::COLUMN_NAME_INVALID))->format(self::printable($column));
                }
                $this->columns[] = $columnsName;
            }
        }
        if (! ($isSelect = $selectStatement instanceof SelectStatement) && ! $selectStatement instanceof RawBuilder) {
            throw (new QueryException(QueryException::SELECT_STRUCT_INVALID))->format(self::printable($selectStatement));
        }
        $this->mergeParams($selectStatement->getParams());
        $this->pushCondition($selectStatement);
        $this->logHasSubQuery($isSelect);
    }

    public function getColumns() {
        return $this->columns;
    }

    public function __toString(): string {
        $columnsSql = empty($this->columns) ? '' : '(' .implode(',', $this->columns). ') ';
        $selectSql = current($this->getConditions());
        return "{$columnsSql}{$selectSql}";
    }
}
