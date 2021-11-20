<?php
/**
 * Author: Drunk
 * Date: 2019/8/20 15:45
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;

class OrderSchema extends SchemaAbstract {
    public function addOrder(string|array|RawBuilder $column, string|bool|null $order, bool $isAutoRaw) {
        if (is_bool($order)) {
            $isAutoRaw = $order;
            $order = null;
        }
        $conditions = is_array($column) ? $column : [[$column, $order]];
        foreach ($conditions as $condition) {
            if (is_array($condition)) {
                $column = $condition[0] ?? null;
                $column && $isAutoRaw && is_string($column) && $column = new RawBuilder($column, false);
                $column = ($isRaw = $column instanceof RawBuilder) ? $column : self::tableWrap($column);
                ! $column && throw (new QueryException(QueryException::ORDER_BY_INVALID))->format(self::printable($condition[0] ?? ''));
                $order = strtoupper(trim($condition[1] ?? ''));
                $this->pushCondition($column . (in_array($order, ['ASC', 'DESC']) ? " {$order}" : ''));
                $isRaw && $this->mergeParams($column->getParams());
            } else if (($isRaw = $condition instanceof RawBuilder) || is_string($condition) && $column = self::tableWrap($condition)) {
                $this->pushCondition($isRaw ? $condition : $column);
                $isRaw && $this->mergeParams($condition->getParams());
            } else {
                throw (new QueryException(QueryException::ORDER_BY_INVALID))->format(self::printable($condition));
            }
        }
    }

    public function __toString(): string {
        return implode(',', $this->getConditions());
    }
}
