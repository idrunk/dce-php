<?php
/**
 * Author: Drunk
 * Date: 2019/8/21 19:26
 */

namespace dce\db\query\builder\Statement;

use dce\db\query\QueryException;
use dce\db\query\builder\schema\DeleteSchema;
use dce\db\query\builder\schema\JoinSchema;
use dce\db\query\builder\schema\LimitSchema;
use dce\db\query\builder\schema\OrderSchema;
use dce\db\query\builder\schema\TableSchema;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\StatementAbstract;

class DeleteStatement extends StatementAbstract {
    private bool|null $allowEmptyConditionOrMustEqual;

    public function __construct(
        DeleteSchema $deleteSchema,
        TableSchema $tableSchema,
        JoinSchema $joinSchema,
        WhereSchema $whereSchema,
        OrderSchema $orderSchema,
        LimitSchema $limitSchema,
        bool|null $allowEmptyConditionOrMustEqual
    ) {
        $this->deleteSchema = $deleteSchema;
        $this->tableSchema = $tableSchema;
        $this->joinSchema = $joinSchema;
        $this->whereSchema = $whereSchema;
        $this->orderSchema = $orderSchema;
        $this->limitSchema = $limitSchema;
        $this->allowEmptyConditionOrMustEqual = $allowEmptyConditionOrMustEqual;
        $this->valid();
        $this->mergeParams($this->joinSchema->getParams());
        $this->mergeParams($this->whereSchema->getParams());
        $this->logHasSubQuery($this->joinSchema->hasSubQuery());
        $this->logHasSubQuery($this->whereSchema->hasSubQuery());
    }

    public function __toString(): string {
        $sql = "DELETE";
        if (! $this->deleteSchema->isEmpty()) {
            $sql .= " {$this->deleteSchema}";
        }
        $sql .= " FROM {$this->tableSchema}";
        if (! $this->joinSchema->isEmpty()) {
            $sql .= " {$this->joinSchema}";
        }
        if (! $this->whereSchema->isEmpty()) {
            $sql .= " WHERE {$this->whereSchema}";
        }
        if (! $this->orderSchema->isEmpty()) {
            $sql .= " ORDER BY {$this->orderSchema}";
        }
        if (! $this->limitSchema->isEmpty()) {
            $sql .= " LIMIT {$this->limitSchema}";
        }
        return $sql;
    }

    protected function valid(): void {
        if ($this->tableSchema->isEmpty()) {
            throw new QueryException(QueryException::DELETE_TABLE_NOT_SPECIFIED);
        }
        if (count($this->tableSchema->getConditions()) > 1 || ! $this->joinSchema->isEmpty()) {
            if (! $this->orderSchema->isEmpty() || ! $this->limitSchema->isEmpty()) {
                throw new QueryException(QueryException::CANNOT_DELETE_WITH_MULTIPLE_SORTED_TABLE);
            }
        } else {
            // mark Mysql的Delete语句, 单表不能用别名, 多表可以
            if (!empty($this->tableSchema->getConditions()[0]['alias'])) {
                throw new QueryException(QueryException::DELETE_TABLE_NOT_SUPPORT_ALIAS);
            }
        }
        if (! $this->allowEmptyConditionOrMustEqual) {
            if ($this->whereSchema->isEmpty()) {
                throw new QueryException(QueryException::EMPTY_DELETE_FULL_NOT_ALLOW);
            }
            if (false === $this->allowEmptyConditionOrMustEqual && ! $this->whereSchema->hasEqual()) {
                throw new QueryException(QueryException::NO_EQUAL_DELETE_NOT_ALLOW);
            }
        }
    }
}
