<?php
/**
 * Author: Drunk
 * Date: 2019/10/10 15:11
 */

namespace dce\db\query\builder\schema;

use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class WhereConditionSchema extends SchemaAbstract {
    public string|null $column;

    public function __construct(
        public string|false|RawBuilder $columnWrapped,
        public string|int|float|false|RawBuilder|SelectStatement|null $operator,
        public string|int|float|array|false|RawBuilder|SelectStatement|null $value,
        public string|null $placeHolder = null,
    ) {
        $this->column = trim($columnWrapped, '`');
    }

    public function __toString(): string {
        $columnSql = $this->columnWrapped ? "{$this->columnWrapped} " : '';
        return "{$columnSql}{$this->operator} {$this->placeHolder}";
    }
}
