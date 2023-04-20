<?php
/**
 * Author: Drunk
 * Date: 2019/7/29 12:31
 */

namespace dce\db\query\builder\Statement;

use dce\db\query\QueryException;
use dce\db\query\builder\schema\InsertSchema;
use dce\db\query\builder\schema\TableSchema;
use dce\db\query\builder\StatementAbstract;

class InsertStatement extends StatementAbstract {
    private bool|null $updateOrIgnore;
    private array $updateColumns;

    public function __construct(TableSchema $tableSchema, InsertSchema $insertSchema, bool|array|null $updateOrIgnore) {
        $this->tableSchema = $tableSchema;
        $this->insertSchema = $insertSchema;
        $this->updateOrIgnore = $updateOrIgnore || is_array($updateOrIgnore) ?: $updateOrIgnore;
        $this->valid();
        $this->updateOrIgnore && $this->setConflictUpdate(is_array($updateOrIgnore) ? $updateOrIgnore : []);
        $this->mergeParams($this->insertSchema->getParams());
        $this->logHasSubQuery($this->insertSchema->hasSubQuery());
    }

    private function setConflictUpdate(array $excludeColumns): void {
        $this->updateColumns = array_filter($this->insertSchema->getColumns(), fn($c) => ! in_array($c, $excludeColumns), ARRAY_FILTER_USE_KEY);
        ! $this->updateColumns && throw new QueryException(QueryException::CONFLICT_UPDATE_COLUMNS_CANNOT_BE_EMPTY);
    }

    public function isBatch(): bool {
        return $this->insertSchema->isBatchInsert();
    }

    public function getUpdateOrIgnore(): bool|null {
        return $this->updateOrIgnore;
    }

    public function __toString(): string {
        $insertSql = $this->updateOrIgnore === false ? 'INSERT IGNORE' : 'INSERT';
        $updateSql = $this->updateOrIgnore ? ' AS EXCLUDED ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c=EXCLUDED.$c", $this->updateColumns)) : '';
        return "$insertSql INTO $this->tableSchema $this->insertSchema$updateSql";
    }

    protected function valid(): void {
        $this->tableSchema->isEmpty() && throw new QueryException(QueryException::INSERT_TABLE_NOT_SPECIFIED);
        $this->insertSchema->isEmpty() && throw new QueryException(QueryException::NO_INSERT_DATA);
    }
}
