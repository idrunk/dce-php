<?php
/**
 * Author: Drunk
 * Date: 2019/8/21 18:18
 */

namespace dce\db\query\builder\Statement;

use dce\db\query\QueryException;
use dce\db\query\builder\schema\JoinSchema;
use dce\db\query\builder\schema\LimitSchema;
use dce\db\query\builder\schema\OrderSchema;
use dce\db\query\builder\schema\TableSchema;
use dce\db\query\builder\schema\UpdateSchema;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\StatementAbstract;

class UpdateStatement extends StatementAbstract {
    private bool|null $allowEmptyConditionOrMustEqual;

    public function __construct(
        TableSchema $tableSchema,
        JoinSchema $joinSchema,
        UpdateSchema $updateSchema,
        WhereSchema $whereSchema,
        OrderSchema $orderSchema,
        LimitSchema $limitSchema,
        bool|null $allowEmptyConditionOrMustEqual
    ) {
        $this->tableSchema = $tableSchema;
        $this->joinSchema = $joinSchema;
        $this->updateSchema = $updateSchema;
        $this->whereSchema = $whereSchema;
        $this->orderSchema = $orderSchema;
        $this->limitSchema = $limitSchema;
        $this->allowEmptyConditionOrMustEqual = $allowEmptyConditionOrMustEqual;
        $this->valid();
        $this->mergeParams($this->joinSchema->getParams());
        $this->mergeParams($this->updateSchema->getParams());
        $this->mergeParams($this->whereSchema->getParams());
        $this->logHasSubQuery($this->joinSchema->hasSubQuery());
        $this->logHasSubQuery($this->updateSchema->hasSubQuery());
        $this->logHasSubQuery($this->whereSchema->hasSubQuery());
    }

    public function __toString(): string {
        $sql = "UPDATE {$this->tableSchema}";
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
            throw new QueryException('未配置更新表', 1);
        }
        if ($this->updateSchema->isEmpty()) {
            throw new QueryException('未传入更新数据', 1);
        }
        if ((count($this->tableSchema->getConditions()) > 1 || ! $this->joinSchema->isEmpty()) && ! ($this->orderSchema->isEmpty() && $this->limitSchema->isEmpty())) {
            throw new QueryException('多表无法排序更新指定条数', 1);
        }
        if (! $this->allowEmptyConditionOrMustEqual) {
            if ($this->whereSchema->isEmpty()) {
                throw new QueryException('当前设置不允许空条件更新全表', 1);
            }
            if (false === $this->allowEmptyConditionOrMustEqual && ! $this->whereSchema->hasEqual()) {
                throw new QueryException('当前设置不允许无等于条件更新数据', 1);
            }
        }
    }
}
