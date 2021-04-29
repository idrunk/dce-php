<?php
/**
 * Author: Drunk
 * Date: 2019/8/21 16:38
 */

namespace dce\db\query\builder\Statement;

use dce\db\query\QueryException;
use dce\db\query\builder\schema\InsertSelectSchema;
use dce\db\query\builder\schema\TableSchema;
use dce\db\query\builder\StatementAbstract;

class InsertSelectStatement extends StatementAbstract {
    private bool|null $ignoreOrReplace;

    public function __construct(TableSchema $tableSchema, InsertSelectSchema $insertSelectSchema, bool|null $ignoreOrReplace) {
        $this->tableSchema = $tableSchema;
        $this->insertSelectSchema = $insertSelectSchema;
        $this->ignoreOrReplace = $ignoreOrReplace;
        $this->valid();
        $this->mergeParams($this->insertSelectSchema->getParams());
        $this->logHasSubQuery($this->insertSelectSchema->hasSubQuery());
    }

    public function __toString(): string {
        $insertSql = $this->ignoreOrReplace ? 'INSERT IGNORE' : (null === $this->ignoreOrReplace ? 'INSERT' : 'REPLACE');
        $sql = "{$insertSql} INTO {$this->tableSchema} {$this->insertSelectSchema}";
        return $sql;
    }

    protected function valid(): void {
        if ($this->tableSchema->isEmpty()) {
            throw new QueryException(QueryException::INSERT_TABLE_NOT_SPECIFIED);
        }
        if ($this->insertSelectSchema->isEmpty()) {
            throw new QueryException(QueryException::SELECT_TABLE_NOT_SPECIFIED);
        }
    }
}
