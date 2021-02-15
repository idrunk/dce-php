<?php
/**
 * Author: Drunk
 * Date: 2019/8/1 12:04
 */

namespace dce\db\query\builder\Statement;

use dce\db\query\QueryException;
use dce\db\query\builder\schema\GroupSchema;
use dce\db\query\builder\schema\HavingSchema;
use dce\db\query\builder\schema\JoinSchema;
use dce\db\query\builder\schema\LimitSchema;
use dce\db\query\builder\schema\OrderSchema;
use dce\db\query\builder\schema\SelectModifierSchema;
use dce\db\query\builder\schema\SelectSchema;
use dce\db\query\builder\schema\TableSchema;
use dce\db\query\builder\schema\UnionSchema;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\StatementAbstract;

class SelectStatement extends StatementAbstract {
    public function __construct(
        SelectSchema $selectSchema,
        SelectModifierSchema $selectModifierSchema,
        TableSchema $tableSchema,
        JoinSchema $joinSchema,
        WhereSchema $whereSchema,
        GroupSchema $groupSchema,
        HavingSchema $havingSchema,
        OrderSchema $orderSchema,
        LimitSchema $limitSchema,
        UnionSchema $unionSchema
    ) {
        $this->selectSchema = $selectSchema;
        $this->selectModifierSchema = $selectModifierSchema;
        $this->tableSchema = $tableSchema;
        $this->joinSchema = $joinSchema;
        $this->whereSchema = $whereSchema;
        $this->groupSchema = $groupSchema;
        $this->havingSchema = $havingSchema;
        $this->orderSchema = $orderSchema;
        $this->limitSchema = $limitSchema;
        $this->unionSchema = $unionSchema;
        $this->valid();
        $this->mergeParams($this->tableSchema->getParams());
        $this->mergeParams($this->joinSchema->getParams());
        $this->mergeParams($this->whereSchema->getParams());
        $this->mergeParams($this->havingSchema->getParams());
        $this->mergeParams($this->unionSchema->getParams());
        $this->logHasSubQuery($this->tableSchema->hasSubQuery());
        $this->logHasSubQuery($this->joinSchema->hasSubQuery());
        $this->logHasSubQuery($this->whereSchema->hasSubQuery());
        $this->logHasSubQuery($this->havingSchema->hasSubQuery());
        $this->logHasSubQuery($this->unionSchema->hasSubQuery());
    }

    public function __toString(): string {
        $sql = 'SELECT';
        if (! $this->selectModifierSchema->isEmpty()) {
            $sql .= " {$this->selectModifierSchema}";
        }
        $sql .= " {$this->selectSchema}";
        if (! $this->tableSchema->isEmpty()) {
            $sql .= " FROM {$this->tableSchema}";
            if (! $this->joinSchema->isEmpty()) {
                $sql .= " {$this->joinSchema}";
            }
            if (! $this->whereSchema->isEmpty()) {
                $sql .= " WHERE {$this->whereSchema}";
            }
            if (! $this->groupSchema->isEmpty()) {
                $sql .= " GROUP BY {$this->groupSchema}";
            }
            if (! $this->havingSchema->isEmpty()) {
                $sql .= " HAVING {$this->havingSchema}";
            }
            if (! $this->orderSchema->isEmpty()) {
                $sql .= " ORDER BY {$this->orderSchema}";
            }
            if (! $this->limitSchema->isEmpty()) {
                $sql .= " LIMIT {$this->limitSchema}";
            }
        }
        if (! $this->unionSchema->isEmpty()) {
            $sql = "($sql) {$this->unionSchema}";
        }
        return $sql;
    }

    protected function valid(): void {
        if ($this->selectSchema->isEmpty()) {
            throw new QueryException('无效查询语句, 缺少查询字段', 1);
        }
    }
}
