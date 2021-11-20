<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/7/21 11:11
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;

class SelectSchema extends SchemaAbstract {
    public function __construct(string|array|RawBuilder|null $columns, bool $isAutoRaw) {
        if (empty($columns)) {
            $columns = ['*'];
        } else if (is_string($columns)) {
            $columns = explode(',', $columns);
        } else if (! is_array($columns)) {
            $columns = [$columns];
        }
        foreach ($columns as $alias => $column) {
            $isAutoRaw && is_string($column) && $column = new RawBuilder($column, false);
            $this->extendColumn($column, is_int($alias) ? null : $alias);
        }
    }

    public function extendColumn(string|RawBuilder $column, string|null $alias = null) {
        if (($isRaw = $column instanceof RawBuilder) || is_numeric($column)) {
            $columnName = $column;
        } else if (! (is_string($column) && $columnName = self::tableWrap($column, true))) {
            throw (new QueryException(QueryException::SELECT_COLUMN_INVALID))->format(self::printable($column));
        }
        $columnName .= $alias ? " {$alias}" : '';
        if (! in_array($columnName, $this->getConditions())) {
            $this->pushCondition($columnName);
            $isRaw && $this->mergeParams($column->getParams());
        }
    }

    /**
     * 将查询字段转为查询结果的数组键名
     * @param string|RawBuilder $column
     * @return mixed
     */
    public function columnToKey(string|RawBuilder $column) {
        if (! is_string($column) || ! $columnParts = self::tableNameParse($column)) {
            return $column;
        }
        [, $columnName, $alias] = $columnParts;
        return $alias ?: $columnName;
    }

    public function __toString(): string {
        return implode(',', $this->getConditions());
    }
}
