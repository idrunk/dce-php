<?php
/**
 * Author: Drunk
 * Date: 2019/7/29 10:46
 */

namespace dce\db\query\builder;

use dce\db\query\QueryException;

abstract class SchemaAbstract implements SchemaInterface {
    private array $conditions = [];

    protected array $sqlPackage = [];

    private array $params = [];

    private bool $subQueryBool = false;

    public function isEmpty() {
        return empty($this->conditions);
    }

    public function hasSubQuery(): bool {
        return $this->subQueryBool;
    }

    protected function logHasSubQuery(bool $isSubQuery): void {
        if (! $this->subQueryBool && $isSubQuery) {
            $this->subQueryBool = true;
        }
    }

    final public function getConditions(): array {
        return $this->conditions;
    }

    final protected function pushCondition(string|array|int|StatementAbstract $condition, bool|null $unshiftOrEmpty = null): void {
        if (false === $unshiftOrEmpty) {
            $this->conditions = [];
        }
        if ($unshiftOrEmpty) {
            array_unshift($this->conditions, $condition);
        } else {
            $this->conditions[] = $condition;
        }
    }

    final protected function mergeConditions(array $params): void {
        $this->conditions = array_merge($this->params, $params);
    }

    final public function getParams(): array {
        return $this->params;
    }

    final protected function pushParam($param): void {
        $this->params[] = $param;
    }

    final protected function mergeParams(array $params): void {
        $this->params = array_merge($this->params, $params);
    }

    protected static function tableNameParse(string $string): array|false {
        if (!preg_match('/^\s*(?:(`?)(\w+)\1\.)?(`?)([\w]+|\*)\3(?: +(?:as +)?(`?)(\w+)\5)?\s*?($)/ui', $string, $parts)) {
            return false;
        }
        [, , $db_name, , $table_name, , $alias] = $parts;
        return [$db_name, $table_name, $alias];
    }

    protected static function tableWrap(string|null $string, bool $isAllowAlias = false): string|false|null {
        if (! is_string($string)) {
            return null;
        }
        if (! $tableNameParts = self::tableNameParse($string)) {
            return false;
        }
        [$db_name, $table_name, $alias] = $tableNameParts;
        if ((! $isAllowAlias || '*' === $table_name) && $alias) {
            return false;
        }
        return ($db_name ? "`$db_name`." : '') . sprintf($table_name === '*' ? '%s' : '`%s`', $table_name) . ($alias ? " AS `$alias`" : '');
    }

    public static function tableWrapThrow(string|int|float|null $string, bool $isAllowAlias = false): string|int|float {
        if (is_numeric($string)) {
            $table = $string;
        } else {
            $table = self::tableWrap($string, $isAllowAlias);
            if (!$table) {
                throw new QueryException("{$string} 非法");
            }
        }
        return $table;
    }

    /**
     * 将数据转为可打印的类型并返回
     * @param $value
     * @return string
     */
    final public static function printable($value): string {
        if (is_object($value)) {
            $value = get_class($value);
        } else if (is_array($value)) {
            $value = 'Array';
        }
        return $value;
    }
}
