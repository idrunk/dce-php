<?php
/**
 * Author: Drunk
 * Date: 2019-12-20 15:17
 */

namespace dce\sharding\parser\mysql;

use dce\sharding\parser\MysqlParser;

abstract class MysqlStatementParser extends MysqlParser {
    protected static string $caseStmt = 'CASE';

    protected static string $whenStmt = 'WHEN';

    private static function detect($statementName): string|false {
        return match(strtoupper($statementName)) {
            self::$caseStmt => __NAMESPACE__ . '\statement\MysqlCaseParser',
            self::$whenStmt => __NAMESPACE__ . '\statement\MysqlWhenParser',
            default => false,
        };
    }

    public static function build(string $statement, int & $offset, string|null $statementName = null): static|null {
        if (is_subclass_of(static::class, self::class)) {
            $class = static::class;
        } else {
            $class = self::detect($statementName);
        }
        if ($class) {
            $instance = new $class($statement, $offset);
            if ($instance instanceof self) {
                $instance->parse();
                return $instance;
            }
        }
        return null;
    }

    abstract protected function parse(): void;
}
