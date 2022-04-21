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

    public function isEmpty(): bool {
        return empty($this->conditions);
    }

    public function hasSubQuery(): bool {
        return $this->subQueryBool;
    }

    protected function logHasSubQuery(bool $isSubQuery): void {
        ! $this->subQueryBool && $isSubQuery && $this->subQueryBool = true;
    }

    final public function getConditions(): array {
        return $this->conditions;
    }

    final protected function pushCondition(string|array|int|StatementAbstract|false $condition, bool|null $unshiftOrEmpty = null): void {
        if (false === $unshiftOrEmpty || false === $condition) {
            $this->conditions = [];
            if (false === $condition) return;
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

    final protected function pushParam(mixed $param): void {
        $this->params[] = $param;
    }

    final protected function mergeParams(array $params): void {
        $this->params = array_merge($this->params, $params);
    }

    protected static function tableNameParse(string $string): array|false {
        if (! preg_match('/^\s*(?:(`?)(\w+)\1\.)?(`?)([\w]+|\*)\3(?: +(?:as +)?(`?)(\w+)\5)?\s*?($)/ui', $string, $parts))
            return false;
        [, , $dbName, , $tableName, , $alias] = $parts;
        return [$dbName, $tableName, $alias];
    }

    protected static function tableWrap(string|null $string, bool $isAllowAlias = false): string|false|null {
        if (! is_string($string)) return null;
        if (! $tableNameParts = self::tableNameParse($string)) return false;
        [$dbName, $tableName, $alias] = $tableNameParts;
        if ((! $isAllowAlias || '*' === $tableName) && $alias) return false;
        return ($dbName ? "`$dbName`." : '') . sprintf($tableName === '*' ? '%s' : '`%s`', $tableName) . ($alias ? " AS `$alias`" : '');
    }

    public static function tableWrapThrow(string|int|float|null $string, bool $isAllowAlias = false): string|int|float {
        if (is_numeric($string)) {
            $table = $string;
        } else {
            ! $table = self::tableWrap($string, $isAllowAlias) && throw (new QueryException(QueryException::TABLE_OR_COLUMN_INVALID))->format($string);
        }
        return $table;
    }

    /**
     * 将数据转为可打印的类型并返回
     * @param mixed $value
     * @return string
     */
    final public static function printable(mixed $value): string {
        if (is_object($value)) {
            $value = get_class($value);
        } else if (is_array($value)) {
            $value = 'Array';
        }
        return $value;
    }
}
