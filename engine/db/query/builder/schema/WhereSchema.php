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
        if (! $column) {
            return;
        }
        $condition = is_array($column)
            ? ( is_array($column[0] ?? 0) ? $column : [$column] )
            : [[$column, $operator, $value]];
        if (! empty($this->getConditions())) {
            $logic = strtoupper($logic);
            $this->sqlPackage[] = $logic;
            $this->pushCondition($logic);
        }
        [$conditionSqlPackage, $conditionPackage, $params] = $this->conditionPack($condition);
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
            $isRawCondition = $condition instanceof RawBuilder;
            $isWhereSchema = $condition instanceof WhereSchema;
            if ($isRawCondition || $isWhereSchema || is_array($condition)) {
                if (count($conditionPackage) % 2) {
                    // 当当前条件项为查询条件, 且已压入的条件包为单数时, 则表示尚未压入逻辑运算符, 则补上默认的'AND'
                    $conditionPackage[] = $conditionSqlPackage[] = 'AND';
                }
                if ($isRawCondition || $isWhereSchema) {
                    // 条件包为原生条件语句, 或条件包为查询条件实例
                    $conditionPackage[] = $conditionSqlPackage[] = $isRawCondition ? "$condition" : "($condition)";
                    $params = array_merge($params, $condition->getParams());
                } else {
                    $column = $columnName = $condition[0] ?? false;
                    if (is_array($column)) {
                        // 当字段名为数组时, 表示当前条件为子条件包, 进行递归
                        [$subConditionSqlPackage, $subConditionPackage, $conditionPackageParams] = $this->conditionPack($condition);
                        $conditionSqlPackage[] = $subConditionSqlPackage;
                        $conditionPackage[] = $subConditionPackage;
                        $params = array_merge($params, $conditionPackageParams);
                    } else if ($column instanceof WhereSchema) {
                        $conditionPackage[] = $conditionSqlPackage[] = (string) $column;
                        $params = $column->getParams();
                    } else if (($leftIsRaw = $column instanceof RawBuilder) || is_string($column)) {
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
                        [$conditionInstance, $conditionParams] = $this->conditionBuild($column, $operator, $value);
                        $conditionPackage[] = $conditionSqlPackage[] = $conditionInstance;
                        $params = array_merge($params, $conditionParams);
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
        $conditionPackageSqlTmpl = count($conditionPackage) > 1 ? '(%s)' : '%s';
        $conditionSqlPackage = sprintf($conditionPackageSqlTmpl, implode(' ', $conditionSqlPackage));
        return [$conditionSqlPackage, $conditionPackage, $params];
    }

    /**
     * 构建查询条件及提取参数
     * @param string|false|RawBuilder $column
     * @param string|int|float|false|RawBuilder|SelectStatement|null $operator
     * @param string|int|float|array|false|RawBuilder|SelectStatement|null $value
     * @return array
     * @throws QueryException
     */
    private function conditionBuild(string|false|RawBuilder $column, string|int|float|false|RawBuilder|SelectStatement|null $operator, string|int|float|array|false|RawBuilder|SelectStatement|null $value) {
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
        $params = [];
        switch ($operator) {
            case '=':
                $this->hasEqualOperator = true;
            case '>':
            case '>=':
            case '<':
            case '<=':
            case '<>':
            case 'LIKE':
            case 'NOT LIKE':
                if (null === $value && in_array($operator, ['=', '<>'])) {
                    $condition->operator = $operator === '=' ? 'IS' : 'IS NOT';
                    $condition->placeHolder = 'NULL';
                } else {
                    [$placeHolder, $params] = $this->rightValueHandle($value, $operator);
                    $condition->placeHolder = $placeHolder;
                }
                break;
            case 'BETWEEN':
            case 'NOT BETWEEN':
                if (is_array($value)) {
                    [$placeHolder, $params] = $this->rightValueHandle($value[0] ?? false, $operator);
                    [$placeHolderLast, $paramsLast] = $this->rightValueHandle($value[1] ?? false, $operator);
                    $condition->placeHolder = "{$placeHolder} AND {$placeHolderLast}";
                    $params = array_merge($params, $paramsLast);
                } else {
                    $condition->placeHolder = self::printable($value);
                    throw (new QueryException(QueryException::RIGHT_VALUE_INVALID))->format($operator, $condition);
                }
                break;
            case 'IN':
                $this->hasEqualOperator = true;
            case 'NOT IN':
                if (is_array($value) && !empty($value)) {
                    $values = [];
                    foreach ($value as $v) {
                        [$placeHolder, $paramsCurrent] = $this->rightValueHandle($v, $operator);
                        $values[] = $placeHolder;
                        $params = array_merge($params, $paramsCurrent);
                    }
                    $condition->placeHolder = '('.implode(',', $values).')';
                } else if (($isRaw = $value instanceof RawBuilder) || $value instanceof SelectStatement) {
                    $condition->placeHolder = $isRaw ? "$value" : "($value)";
                    $params = $value->getParams();
                    $this->logHasSubQuery(! $isRaw);
                } else {
                    $condition->placeHolder = self::printable($value);
                    throw (new QueryException(QueryException::RIGHT_VALUE_INVALID))->format($operator, $condition);
                }
                break;
            case 'EXISTS': // 无左值
            case 'NOT EXISTS':
                if (($isRaw = $value instanceof RawBuilder) || $value instanceof SelectStatement) {
                    $condition->columnPure = null;
                    $condition->placeHolder = $isRaw ? "$value" : "($value)";
                    $params = $value->getParams();
                    $this->logHasSubQuery(! $isRaw);
                } else {
                    $condition->placeHolder = self::printable($value);
                    throw (new QueryException(QueryException::RIGHT_VALUE_INVALID))->format($operator, $condition);
                }
                break;
            case false: // 仅有左值的情况
                $condition->operator = null;
                break;
            default: // 没有匹配到的为暂不支持的操作
                throw (new QueryException(QueryException::COMPARE_OPERATOR_INVALID))->format($operator);
        }
        return [$condition, $params];
    }

    /**
     * 处理右值
     * @param string|int|float|RawBuilder|SelectStatement $value
     * @param string|false $operator
     * @return array
     * @throws QueryException
     */
    private function rightValueHandle(string|int|float|RawBuilder|SelectStatement $value, string|false $operator) {
        if (is_string($value) || is_numeric($value)) {
            $rightValue = '?';
            $params = [$value];
        } else if (($isRaw = $value instanceof RawBuilder) || $value instanceof SelectStatement) {
            $rightValue = $isRaw ? "$value" : "($value)";
            $params = $value->getParams();
            $this->logHasSubQuery(! $isRaw);
        } else {
            $value = self::printable($value);
            throw (new QueryException(QueryException::RIGHT_VALUE_INVALID))->format($operator, $value);
        }
        return [$rightValue, $params];
    }
}
