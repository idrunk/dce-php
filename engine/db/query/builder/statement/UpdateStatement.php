<?php
/**
 * Author: Drunk
 * Date: 2019/8/21 18:18
 */

namespace dce\db\query\builder\Statement;

use dce\db\query\builder\schema\JoinSchema;
use dce\db\query\builder\schema\LimitSchema;
use dce\db\query\builder\schema\OrderSchema;
use dce\db\query\builder\schema\TableSchema;
use dce\db\query\builder\schema\UpdateSchema;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\schema\WithSchema;
use dce\db\query\builder\StatementAbstract;
use dce\db\query\QueryException;

class UpdateStatement extends StatementAbstract {
    private bool|null $allowEmptyConditionOrMustEqual;

    public function __construct(
        TableSchema $tableSchema,
        JoinSchema $joinSchema,
        UpdateSchema $updateSchema,
        WhereSchema $whereSchema,
        OrderSchema $orderSchema,
        LimitSchema $limitSchema,
        WithSchema $withSchema,
        bool|null $allowEmptyConditionOrMustEqual,
    ) {
        $this->withSchema = $withSchema;
        $this->tableSchema = $tableSchema;
        $this->joinSchema = $joinSchema;
        $this->updateSchema = $updateSchema;
        $this->whereSchema = $whereSchema;
        $this->orderSchema = $orderSchema;
        $this->limitSchema = $limitSchema;
        $this->allowEmptyConditionOrMustEqual = $allowEmptyConditionOrMustEqual;
        $this->valid();
        $this->mergeParams($this->withSchema->getParams());
        $this->mergeParams($this->joinSchema->getParams());
        $this->mergeParams($this->updateSchema->getParams());
        $this->mergeParams($this->whereSchema->getParams());
        $this->logHasSubQuery($this->joinSchema->hasSubQuery());
        $this->logHasSubQuery($this->updateSchema->hasSubQuery());
        $this->logHasSubQuery($this->whereSchema->hasSubQuery());
    }

    public function __toString(): string {
        $sql = $this->withSchema->isEmpty() ? '' : "WITH $this->withSchema\n";
        $sql .= "UPDATE {$this->tableSchema}";
        if (! $this->joinSchema->isEmpty()) {
            $sql .= " {$this->joinSchema}";
        }
        $sql .= " SET {$this->updateSchema}";
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
            throw new QueryException(QueryException::UPDATE_TABLE_NOT_SPECIFIED);
        }
        if ($this->updateSchema->isEmpty()) {
            throw new QueryException(QueryException::NO_UPDATE_DATA);
        }
        if ((count($this->tableSchema->getConditions()) > 1 || ! $this->joinSchema->isEmpty()) && ! ($this->orderSchema->isEmpty() && $this->limitSchema->isEmpty())) {
            throw new QueryException(QueryException::CANNOT_UPDATE_WITH_MULTIPLE_SORTED_TABLE);
        }
        if (! $this->allowEmptyConditionOrMustEqual) {
            if ($this->whereSchema->isEmpty()) {
                throw new QueryException(QueryException::EMPTY_UPDATE_FULL_NOT_ALLOW);
            }
            if (false === $this->allowEmptyConditionOrMustEqual && ! $this->whereSchema->hasEqual()) {
                throw new QueryException(QueryException::NO_EQUAL_UPDATE_NOT_ALLOW);
            }
        }
    }
}
