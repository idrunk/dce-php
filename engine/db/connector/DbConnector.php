<?php
/**
 * Author: Drunk
 * Date: 2019/8/26 10:41
 */

namespace dce\db\connector;

use Closure;
use dce\db\query\builder\StatementAbstract;
use dce\db\query\builder\StatementInterface;

abstract class DbConnector {
    private static StatementAbstract|string $lastStatement;

    private static array|null $lastParams;

    private string $dbName;

    private string $host;

    private int $port;

    protected function logStatement(StatementAbstract|string $statement, array|null $params = null): string {
        self::$lastStatement = $statement;
        self::$lastParams = $params;
        $sql = StatementAbstract::fill($statement, $params);
        $logId = ScriptLogger::trigger([$this->host, $this->port, $this->dbName], $sql);
        return $logId;
    }

    protected function logStatementUpdate(string|int $logId, string|float $result): void {
        ScriptLogger::triggerUpdate($logId, [$this->host, $this->port, $this->dbName], $result);
    }

    protected static function checkInsertStatement(string $statement): bool {
        return preg_match('/^\s*insert\s+/ui', $statement);
    }

    public function connect(string $dbName, string $host, string $username, string $password, int $port = 3306, bool $persistent = true): void {
        if ($dbName) {
            $this->dbName = $dbName;
            $this->host = $host;
            $this->port = $port;
        }
        $this->link($dbName, $host, $username, $password, $port, $persistent);
    }

    public static function getLastStatement(): array {
        return [self::$lastStatement, self::$lastParams];
    }

    abstract protected function link(string|null $dbName, string $host, string $username, string $password, int $port = 3306, bool $persistent = true): object;

    abstract public function getConnection(): object;

    abstract public function queryAll(StatementInterface $statement, string|null $indexColumn = null, string|null $extractColumn = null): array;

    abstract public function queryEach(StatementInterface $statement, Closure|null $decorator = null): DbEachIterator;

    abstract public function queryOne(StatementInterface $statement): array|false;

    abstract public function queryColumn(StatementInterface $statement, int $column = 0): string|float|null|false;

    abstract public function queryGetAffectedCount(StatementInterface $statement): int;

    abstract public function queryGetInsertId(StatementInterface $statement): int|string;

    abstract public function query(string $statement, array $params, array $fetchArgs): array;

    abstract public function execute(string $statement, array $params): int|string;

    abstract public function begin(): bool;

    abstract public function commit(): bool;

    abstract public function rollback(): bool;
}
