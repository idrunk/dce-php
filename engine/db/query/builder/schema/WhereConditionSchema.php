<?php
/**
 * Author: Drunk
 * Date: 2019/10/10 15:11
 */

namespace dce\db\query\builder\schema;

use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;
use dce\db\query\QueryException;

class WhereConditionSchema extends SchemaAbstract {
    public string|null $columnPure;

    public string|null $placeHolder;

    public bool $equalLike = false;

    public function __construct(
        public string|false|RawBuilder $columnWrapped,
        public string|int|float|false|RawBuilder|SelectStatement|null $operator,
        public string|int|float|array|false|RawBuilder|SelectStatement|null $value,
    ) {
        $this->columnPure = preg_replace('/^.*\b(\w+)`?\s*$/u', '$1', $columnWrapped);
        $this->init($operator, $value);
    }

    private function init(string|int|float|false|RawBuilder|SelectStatement|null $operator, string|int|float|array|false|RawBuilder|SelectStatement|null $value): void {
        $params = [];
        switch ($operator) {
            case '=':
                $this->equalLike = true;
            case '>':
            case '>=':
            case '<':
            case '<=':
            case '<>':
            case 'LIKE':
            case 'NOT LIKE':
                if (null === $value && in_array($operator, ['=', '<>'])) {
                    $this->operator = $operator === '=' ? 'IS' : 'IS NOT';
                    $this->placeHolder = 'NULL';
                } else {
                    [$placeHolder, $params] = $this->rightValueHandle($value, $operator);
                    $this->placeHolder = $placeHolder;
                }
                break;
            case 'BETWEEN':
            case 'NOT BETWEEN':
                if (is_array($value)) {
                    [$placeHolder, $params] = $this->rightValueHandle($value[0] ?? false, $operator);
                    [$placeHolderLast, $paramsLast] = $this->rightValueHandle($value[1] ?? false, $operator);
                    $this->placeHolder = "{$placeHolder} AND {$placeHolderLast}";
                    $params = array_merge($params, $paramsLast);
                } else {
                    $this->placeHolder = self::printable($value);
                    throw (new QueryException(QueryException::RIGHT_VALUE_INVALID))->format($operator, $value);
                }
                break;
            case 'IN':
                $this->equalLike = true;
            case 'NOT IN':
                if (is_array($value) && !empty($value)) {
                    $values = [];
                    foreach ($value as $v) {
                        [$placeHolder, $paramsCurrent] = $this->rightValueHandle($v, $operator);
                        $values[] = $placeHolder;
                        $params = array_merge($params, $paramsCurrent);
                    }
                    $this->placeHolder = '('.implode(',', $values).')';
                } else if (($isRaw = $value instanceof RawBuilder) || $value instanceof SelectStatement) {
                    $this->placeHolder = $isRaw ? "$value" : "($value)";
                    $params = $value->getParams();
                    $this->logHasSubQuery(! $isRaw);
                } else {
                    $this->placeHolder = self::printable($value);
                    throw (new QueryException(QueryException::RIGHT_VALUE_INVALID))->format($operator, $value);
                }
                break;
            case 'EXISTS': // 无左值
            case 'NOT EXISTS':
                if (($isRaw = $value instanceof RawBuilder) || $value instanceof SelectStatement) {
                    $this->columnPure = null;
                    $this->placeHolder = $isRaw ? "$value" : "($value)";
                    $params = $value->getParams();
                    $this->logHasSubQuery(! $isRaw);
                } else {
                    $this->placeHolder = self::printable($value);
                    throw (new QueryException(QueryException::RIGHT_VALUE_INVALID))->format($operator, $value);
                }
                break;
            case false: // 仅有左值的情况
                $this->operator = null;
                break;
            default: // 没有匹配到的为暂不支持的操作
                throw (new QueryException(QueryException::COMPARE_OPERATOR_INVALID))->format($operator);
        }
        $this->mergeParams($params);
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

    public function __toString(): string {
        $columnSql = $this->columnWrapped ? "{$this->columnWrapped} " : '';
        return "{$columnSql}{$this->operator} {$this->placeHolder}";
    }
}
