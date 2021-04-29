<?php
/**
 * Author: Drunk
 * Date: 2019/8/21 17:30
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class UpdateSchema extends SchemaAbstract {
    private array $columns;

    public function __construct(array $data) {
        $this->columns = $columns = array_keys($data);

        foreach ($this->columns as $k=>$column) {
            $columnName = self::tableWrap($column);
            if (!$columnName) {
                throw (new QueryException(QueryException::COLUMN_INVALID))->format($column);
            }
            $this->columns[$k] = $columnName;
        }

        foreach ($columns as $k=>$column) {
            $value = $data[$column] ?? null;
            if ($value instanceof RawBuilder) {
                $this->sqlPackage[] = "{$this->columns[$k]}=$value";
            } else if ($value instanceof SelectStatement) {
                $this->sqlPackage[] = "{$this->columns[$k]}=($value)";
                $this->mergeParams($value->getParams());
                $this->logHasSubQuery(true);
            } else {
                $this->sqlPackage[] = "{$this->columns[$k]}=?";
                $this->pushParam($value);
            }
        }

        $this->pushCondition($data);
    }

    public function __toString(): string {
        return implode(',', $this->sqlPackage);
    }
}
