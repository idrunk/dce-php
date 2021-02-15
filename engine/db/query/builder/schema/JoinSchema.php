<?php
/**
 * Author: Drunk
 * Date: 2019/8/20 15:01
 */

namespace dce\db\query\builder\schema;

use dce\db\query\builder\Statement\SelectStatement;
use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;

class JoinSchema extends SchemaAbstract {
    public function addJoin(string|RawBuilder|SelectStatement $tableName, string|null $alias, string|array|RawBuilder|WhereSchema $on, string $type) {
        $tableSchema = new TableSchema();
        $tableSchema->addTable($tableName, $alias);
        if ($on instanceof WhereSchema) {
            $whereSchema = $on;
        } else {
            if (! is_array($on)) {
                if (is_string($on)) {
                    $on = [new RawBuilder($on)];
                } else if ($on instanceof RawBuilder) {
                    $on = [$on];
                } else {
                    $on = [[$on]];
                }
            }
            $whereSchema = new WhereSchema();
            $whereSchema->addCondition($on, false, false, 'AND');
        }
        $typeUpper = strtoupper(trim($type));
        if (!in_array($typeUpper, ['INNER', 'LEFT', 'RIGHT'])) {
            throw new QueryException("INNER类型\"$typeUpper\"无效", 1);
        }
        $this->mergeParams($tableSchema->getParams());
        $this->mergeParams($whereSchema->getParams());
        $this->pushCondition([
            'tableSchema' => $tableSchema,
            'whereSchema' => $whereSchema,
            'joinType' => $typeUpper,
        ]);
    }

    public function __toString(): string {
        $joinConditionSqlPack = [];
        foreach ($this->getConditions() as $condition) {
            $tableSchema = $condition['tableSchema'];
            $whereSchema = $condition['whereSchema'];
            $joinType = $condition['joinType'];
            $joinConditionSqlPack[] = "{$joinType} JOIN {$tableSchema}" . ($whereSchema->isEmpty() ? '' : " ON {$whereSchema}");
        }
        return implode(' ', $joinConditionSqlPack);
    }
}
