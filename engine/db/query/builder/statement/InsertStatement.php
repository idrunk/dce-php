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
    private bool|null $ignoreOrReplace;

    public function __construct(TableSchema $tableSchema, InsertSchema $insertSchema, bool|null $ignoreOrReplace) {
        $this->tableSchema = $tableSchema;
        $this->insertSchema = $insertSchema;
        $this->ignoreOrReplace = $ignoreOrReplace;
        $this->valid();
        $this->mergeParams($this->insertSchema->getParams());
        $this->logHasSubQuery($this->insertSchema->hasSubQuery());
    }

    public function isBatch(): bool {
        return $this->insertSchema->isBatchInsert();
    }

    public function getIgnoreOrReplace(): bool|null {
        return $this->ignoreOrReplace;
    }

    public function __toString(): string {
        $insertSql = $this->ignoreOrReplace ? 'INSERT IGNORE' : (null === $this->ignoreOrReplace ? 'INSERT' : 'REPLACE');
        $sql = "{$insertSql} INTO {$this->tableSchema} {$this->insertSchema}";
        return $sql;
    }

    protected function valid(): void {
        if ($this->tableSchema->isEmpty()) {
            throw new QueryException(QueryException::INSERT_TABLE_NOT_SPECIFIED);
        }
        if ($this->insertSchema->isEmpty()) {
            throw new QueryException(QueryException::NO_INSERT_DATA);
        }
    }
}
