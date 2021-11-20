<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/7/21 11:11
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class TableSchema extends SchemaAbstract {
    public function addTable(string|RawBuilder|SelectStatement $tableName, string|null $alias) {
        [$tableSql, $params] = $this->tablePack($tableName);
        $this->mergeParams($params);
        $this->pushCondition([
            'table' => $tableSql,
            'alias' => self::tableWrap($alias),
        ]);
        return $this;
    }

    public function tablePack(string|RawBuilder|SelectStatement $table) {
        $params = [];
        if (is_string($table) && $tableName = self::tableWrap($table)) {
            $tableSql = $tableName;
        } else if ($table instanceof RawBuilder) {
            $tableSql = "$table";
            $params = $table->getParams();
        } else if ($table instanceof SelectStatement) {
            $tableSql = "($table)";
            $params = $table->getParams();
            $this->logHasSubQuery(true);
        } else {
            throw new QueryException(QueryException::TABLE_INVALID);
        }
        return [$tableSql, $params];
    }

    public function __toString(): string {
        $tableParts = [];
        foreach ($this->getConditions() as $table) {
            $tableParts[] = $table['table'] . ($table['alias'] ? " AS {$table['alias']}" : '');
        }
        $tableSql = implode(',', $tableParts);
        return $tableSql;
    }

    public function getName(): string {
        return trim($this->getConditions()[0]['table'] ?? null, ' `');
    }
}
