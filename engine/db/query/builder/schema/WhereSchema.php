<?php
/**
 * Author: Drunk
 * Date: 2019/8/19 17:54
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;

class WhereSchema extends SchemaAbstract {
    private bool $hasEqualOperator = false;

    public function addCondition(string|array|RawBuilder|WhereSchema $column, string|int|float|false|RawBuilder|SelectStatement $operator, string|int|float|array|false|RawBuilder|SelectStatement $value, string $logic) {
        if (! $column) return;
        if (! empty($this->getConditions())) {
            $logic = strtoupper($logic);
            $this->sqlPackage[] = $logic;
            $this->pushCondition($logic);
        }
        $columnIsRaw = $column instanceof RawBuilder;
        [$conditionSqlPackage, $conditionPackage, $params] = $this->conditionPack(match (true) {
            is_string($column), $columnIsRaw && false !== $operator => [[$column, $operator, $value]],
            is_array($column) && is_string($column[0] ?? null), $columnIsRaw, $column instanceof WhereSchema => [$column],
            default => $column,
        });
        $this->sqlPackage[] = $conditionSqlPackage;
        $this->mergeParams($params);
        $this->pushCondition($conditionPackage);
    }

    public function hasEqual(): bool {
        return $this->hasEqualOperator;
    }

    public function __toString(): string {
        return implode(' ', $this->sqlPackage);
    }

    /**
     * 打包查询条件及提取参数
     * @param array $conditions
     * @return array
     * @throws QueryException
     */
    private function conditionPack(array $conditions) {
        $conditionPackage = $conditionSqlPackage = [];
        $params = [];
        foreach ($conditions as $condition) {
            // 若为WhereSchema, 则直接结构取条件，使主Statement的getConditions()取出来的都是Condition对象
            $condition instanceof WhereSchema && $condition = $condition->getConditions();
            $isArrayCondition = is_array($condition);
            $isRawCondition = $condition instanceof RawBuilder;
            $isCondition = $condition instanceof WhereConditionSchema;
            if ($isArrayCondition || $isRawCondition || $isCondition) {
                if (count($conditionPackage) % 2) {
                    // 当当前条件项为查询条件, 且已压入的条件包为单数时, 则表示尚未压入逻辑运算符, 则补上默认的'AND'
                    $conditionPackage[] = $conditionSqlPackage[] = 'AND';
                }
                if ($isRawCondition || $isCondition) {
                    // 条件包为原生条件语句, 或条件包为查询条件实例
                    $conditionPackage[] = $condition;
                    $conditionSqlPackage[] = $isCondition ? "$condition" : "($condition)";
                    $params = array_merge($params, $condition->getParams());
                } else {
                    $column = $columnName = $condition[0] ?? false;
                    if (($leftIsRaw = $column instanceof RawBuilder) || is_string($column)) {
                        $operator = key_exists(1, $condition) ? $condition[1] : false;
                        $value = key_exists(2, $condition) ? $condition[2] : false;
                        // 原生比较运算左值
                        if (! $leftIsRaw) {
                            $evacuatedUpper = strtoupper(str_replace(' ', '', $column));
                            if (in_array($evacuatedUpper, ['EXISTS', 'NOTEXISTS'])) {
                                // 处理无左比较值的查询条件
                                $column = false;
                                $value = $operator;
                                $operator = $evacuatedUpper;
                            } else {
                                // 处理校验字段名
                                $column = self::tableWrap($column);
                                if (! $column) {
                                    throw (new QueryException(QueryException::LEFT_COMPARE_VALUE_INVALID))->format($columnName);
                                }
                            }
                        }
                        $conditionInstance = $this->conditionBuild($column, $operator, $value);
                        $conditionPackage[] = $conditionSqlPackage[] = $conditionInstance;
                        $params = array_merge($params, $conditionInstance->getParams());
                    } else if ($isArrayCondition || is_array($column)) {
                        // 当字段名为数组时, 表示当前条件为子条件包, 进行递归
                        [$subConditionSqlPackage, $subConditionPackage, $conditionPackageParams] = $this->conditionPack($condition);
                        $conditionSqlPackage[] = $subConditionSqlPackage;
                        $conditionPackage[] = $subConditionPackage;
                        $params = array_merge($params, $conditionPackageParams);
                    } else {
                        throw new QueryException(QueryException::LEFT_SPECIAL_COMPARE_VALUE_INVALID);
                    }
                }
            } else if (is_string($condition) && ($upper = strtoupper(trim($condition))) && in_array($upper, ['AND', 'OR'])) {
                $conditionPackage[] = $conditionSqlPackage[] = $upper;
            } else {
                throw new QueryException(QueryException::WHERE_OR_LOGIC_CONDITION_INVALID);
            }
        }
        $conditionSqlPackage = sprintf(count($conditionPackage) > 1 ? '(%s)' : '%s', implode(' ', $conditionSqlPackage));
        return [$conditionSqlPackage, $conditionPackage, $params];
    }

    /**
     * 构建查询条件及提取参数
     * @param string|false|RawBuilder $column
     * @param string|int|float|false|RawBuilder|SelectStatement|null $operator
     * @param string|int|float|array|false|RawBuilder|SelectStatement|null $value
     * @return WhereConditionSchema
     */
    private function conditionBuild(string|false|RawBuilder $column, string|int|float|false|RawBuilder|SelectStatement|null $operator, string|int|float|array|false|RawBuilder|SelectStatement|null $value): WhereConditionSchema {
        if (false !== $operator) {
            if (false === $value) {
                // 凡是只传两个有效入参的, 皆为等于比较运算符
                $value = $operator;
                $operator = '=';
            }
            $operator = strtoupper(str_replace(' ', '', self::printable($operator)));
            if ('NOT' === substr($operator, 0, 3)) {
                $operator = 'NOT ' . substr($operator, 3);
            }
        }
        $condition = new WhereConditionSchema($column, $operator, $value);
        $condition->hasSubQuery() && $this->logHasSubQuery($condition->hasSubQuery());
        $condition->equalLike && $this->hasEqualOperator = $condition->equalLike;
        return $condition;
    }
}
