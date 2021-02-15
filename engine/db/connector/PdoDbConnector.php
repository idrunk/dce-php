<?php
/**
 * Author: Drunk
 * Date: 2020-02-19 15:35
 */

namespace dce\db\connector;

use Closure;
use dce\db\query\builder\StatementInterface;
use PDO;
use PDOStatement;

class PdoDbConnector extends DbConnector {
    private PDO $connection;

    protected function link(string|null $dbName, string $host, string $username, string $password, int $port = 3306, bool $persistent = true): PDO {
        $dsnParts = [
            "host={$host}",
            "port={$port}",
        ];
        if ($dbName) {
            $dsnParts[] = "dbname={$dbName}";
        }
        $dsn = implode(';', $dsnParts);
        $driverOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // 以抛出异常的方式处理错误
            PDO::ATTR_EMULATE_PREPARES => false, // 关闭不支持预处理的驱动的模拟预处理及数据强制字符串化, 用以按php类型获取数据
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        if ($persistent) {
            $driverOptions[PDO::ATTR_PERSISTENT] = true; // 建立持久连接
        }
        $this->connection = new PDO("mysql:{$dsn}", $username, $password, $driverOptions);
        return $this->connection;
    }

    protected function prepareStatement(StatementInterface $statement, array $attrs = [], PDO|null &$conn = null): PDOStatement {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($statement, $attrs);
        $stmt->execute($statement->getParams());
        return $stmt;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

    public function queryAll(StatementInterface $statement, string|null $indexColumn = null, string|null $extractColumn = null): array {
        $logId = $this->logStatement($statement, $statement->getParams());
        $data = $this->prepareStatement($statement)->fetchAll(PDO::FETCH_ASSOC);
        $this->logStatementUpdate($logId, count($data));
        $newData = [];
        $isValueKey = ! empty($indexColumn);
        $isTakeColumn = ! empty($extractColumn);
        if (!empty($data) && $isValueKey || $isTakeColumn) {
            foreach ($data as $k=>$v) {
                if ($isValueKey) {
                    $k = $v[$indexColumn];
                }
                if ($isTakeColumn) {
                    $v = $v[$extractColumn];
                }
                $newData[$k] = $v;
            }
        }
        return $newData ?: $data;
    }

    public function queryEach(StatementInterface $statement, Closure|null $decorator = null): DbEachIterator {
        $logId = $this->logStatement($statement, $statement->getParams());
        $statement = $this->prepareStatement($statement, [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
        $this->logStatementUpdate($logId, -1);
        return new DbEachIterator($statement, $decorator);
    }

    public function queryOne(StatementInterface $statement): array|false {
        $logId = $this->logStatement($statement, $statement->getParams());
        $stmt = $this->prepareStatement($statement);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $this->logStatementUpdate($logId, (int) !! $data);
        return $data;
    }

    public function queryColumn(StatementInterface $statement, int $column = 0): string|float|null|false {
        $logId = $this->logStatement($statement, $statement->getParams());
        $stmt = $this->prepareStatement($statement);
        $data = $stmt->fetchColumn($column);
        $stmt->closeCursor();
        $this->logStatementUpdate($logId, (int) !! $data);
        return $data;
    }

    public function queryGetAffectedCount(StatementInterface $statement): int {
        $logId = $this->logStatement($statement, $statement->getParams());
        $result = $this->prepareStatement($statement)->rowCount();
        $this->logStatementUpdate($logId, $result);
        return $result;
    }

    public function queryGetInsertId(StatementInterface $statement): int|string {
        $logId = $this->logStatement($statement, $statement->getParams());
        $this->prepareStatement($statement, [], $conn);
        $result = $conn->lastInsertId();
        $this->logStatementUpdate($logId, $result);
        return $result;
    }

    protected function prepare(string $statement, array $params, array $attrs = [], PDO|null &$conn = null): PDOStatement {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($statement, $attrs);
        $stmt->execute($params);
        return $stmt;
    }

    public function query(string $statement, array $params, array $fetchArgs): array {
        $logId = $this->logStatement($statement, $params);
        $result = $this->prepare($statement, $params)->fetchAll(... $fetchArgs);
        $this->logStatementUpdate($logId, count($result));
        return $result;
    }

    public function execute(string $statement, array $params): int|string {
        $logId = $this->logStatement($statement, $params);
        $stmt = $this->prepare($statement, $params, [], $conn);
        if (self::checkInsertStatement($statement)) {
            $result = $conn->lastInsertId();
        } else {
            $result = $stmt->rowCount();
        }
        $this->logStatementUpdate($logId, $result);
        return $result;
    }

    public function begin(): bool {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool {
        return $this->connection->commit();
    }

    public function rollback(): bool {
        return $this->connection->rollBack();
    }
}
