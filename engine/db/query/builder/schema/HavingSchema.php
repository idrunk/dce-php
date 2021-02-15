<?php
/**
 * Author: Drunk
 * Date: 2019/8/20 16:39
 */

namespace dce\db\query\builder\schema;

use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class HavingSchema extends SchemaAbstract {
    private WhereSchema $whereSchema;

    public function __construct() {
        $this->whereSchema = new WhereSchema();
    }

    public function addCondition (string|array|RawBuilder|WhereSchema $column, string|float|RawBuilder|SelectStatement $operator, string|float|RawBuilder|SelectStatement $value) {
        $this->whereSchema->addCondition($column, $operator, $value, 'AND');
        $this->mergeConditions($this->whereSchema->getConditions());
        $this->mergeParams($this->whereSchema->getParams());
    }

    public function __toString(): string {
        return (string) $this->whereSchema;
    }
}
