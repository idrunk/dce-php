<?php
/**
 * Author: Drunk
 * Date: 2019/7/30 11:16
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class InsertSchema extends SchemaAbstract {
    private bool $batchInsert;

    private array $columns;

    public function __construct(array $data) {
        $firstColumn = current($data);
        $this->batchInsert = is_array($firstColumn);
        if (! $this->batchInsert) {
            $firstColumn = $data;
            $data = [$data];
        }
        $this->columns = $columns = array_keys($firstColumn);

        foreach ($this->columns as $k=>$column) {
            $columnName = self::tableWrap($column);
            if (! $columnName) {
                throw (new QueryException(QueryException::COLUMN_INVALID))->format($column);
            }
            $this->columns[$k] = $columnName;
        }

        foreach ($data as $i=>$datum) {
            $placeholders = [];
            foreach ($columns as $column) {
                $value = $datum[$column] ?? null;
                if ($value instanceof RawBuilder) {
                    $placeholders[] = "$value";
                } else if ($value instanceof SelectStatement) {
                    $placeholders[] = "($value)";
                    $this->mergeParams($value->getParams());
                    $this->logHasSubQuery(true);
                } else {
                    $placeholders[] = '?';
                    $this->pushParam($value);
                }
            }
            if (! empty($placeholders)) {
                $this->sqlPackage[] = '(' . implode(',', $placeholders) . ')';
                $this->pushCondition($datum);
            }
        }
    }

    public function isBatchInsert(): bool {
        return $this->batchInsert;
    }

    public function getColumns(): array {
        return $this->columns;
    }

    public function __toString(): string {
        $columnSql = '(' . implode(',', $this->columns) . ')';
        $placeholderSql = implode(',', $this->sqlPackage);
        return "{$columnSql} VALUES {$placeholderSql}";
    }
}
