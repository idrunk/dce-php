<?php
namespace dce\db\query\builder\schema;

use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class WithSchema extends SchemaAbstract {
    public function addWith(string $name, SelectStatement $select, array $columns = [], bool $recursive = null) {
        $name = self::tableWrapThrow($name); // 此处仅作侵入安全校验，不作有效性校验
        $columns = array_map(fn($c) => self::tableWrapThrow($c), $columns);
        $recursive ??= count($select->getUnionSchema()->getConditions()) === 1;
        $this->pushCondition([$recursive ? 'RECURSIVE ' : '', $name, $columns ? '(' . implode(', ', $columns) . ')' : '', $select]);
        $this->mergeParams($select->getParams());
    }

    public function __toString(): string {
        return implode(', ', array_map(fn($c) => "$c[0]$c[1]$c[2] AS ($c[3])", $this->getConditions()));
    }
}