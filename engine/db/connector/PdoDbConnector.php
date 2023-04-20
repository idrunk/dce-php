<?php
/**
 * Author: Drunk
 * Date: 2020-02-19 15:35
 */

namespace dce\db\connector;

use Closure;
use dce\base\SwooleUtility;
use dce\db\query\builder\StatementAbstract;
use dce\db\query\QueryException;
use PDO;
use PDOStatement;
use Swoole\Coroutine;
use Swoole\Timer;

class PdoDbConnector extends DbConnector {
    private PDO $connection;

    protected function link(string|null $dbName, string $host, string $username, string $password, int $port = 3306, bool $persistent = true): PDO {
        $dsnParts = ["host=$host", "port=$port",];
        $dbName && $dsnParts[] = "dbname=$dbName";
        $dsn = implode(';', $dsnParts);
        $driverOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // 以抛出异常的方式处理错误
            PDO::ATTR_EMULATE_PREPARES => false, // 关闭不支持预处理的驱动的模拟预处理及数据强制字符串化, 用以按php类型获取数据
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        $persistent && $driverOptions[PDO::ATTR_PERSISTENT] = true; // 建立持久连接
        $this->connection = new PDO("mysql:$dsn", $username, $password, $driverOptions);
        return $this->connection;
    }

    protected function prepareStatement(StatementAbstract $statement, array $attrs = [], PDO|null &$conn = null): PDOStatement {
        $conn = $this->getConnection();
//        testPoint($conn->errorInfo());
        $stmt = $conn->prepare($statement, $attrs);
//        testPoint(SwooleUtility::inCoroutine() && Coroutine::isCanceled());
        SwooleUtility::inCoroutine() && self::registerCoroutineAutoReleaseOrHandle(Coroutine::getCid(), false);
        $stmt->execute($statement->getParams());
        return $stmt;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

    public function queryAll(StatementAbstract $statement, string|null $indexColumn = null, string|null $extractColumn = null): array {
        $logId = $this->logStatement($statement, $statement->getParams());
        $data = $this->prepareStatement($statement)->fetchAll(PDO::FETCH_ASSOC);
        $this->logStatementUpdate($logId, count($data));
        $newData = [];
        $isValueKey = ! empty($indexColumn);
        $isTakeColumn = ! empty($extractColumn);
        if (!empty($data) && $isValueKey || $isTakeColumn) {
            foreach ($data as $k=>$v) {
                $isValueKey && $k = $v[$indexColumn];
                $isTakeColumn && $v = $v[$extractColumn];
                $newData[$k] = $v;
            }
        }
        return $newData ?: $data;
    }

    public function queryEach(StatementAbstract $statement, Closure|null $decorator = null): DbEachIterator {
        $logId = $this->logStatement($statement, $statement->getParams());
        $statement = $this->prepareStatement($statement, [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
        $this->logStatementUpdate($logId, -1);
        return new DbEachIterator($statement, $decorator);
    }

    public function queryOne(StatementAbstract $statement): array|false {
        $logId = $this->logStatement($statement, $statement->getParams());
        $stmt = $this->prepareStatement($statement);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $this->logStatementUpdate($logId, (int) !! $data);
        return $data;
    }

    public function queryColumn(StatementAbstract $statement, int $column = 0): string|float|null|false {
        $logId = $this->logStatement($statement, $statement->getParams());
        $stmt = $this->prepareStatement($statement);
        $data = $stmt->fetchColumn($column);
        $stmt->closeCursor();
        $this->logStatementUpdate($logId, (int) !! $data);
        return $data;
    }

    public function queryGetAffectedCount(StatementAbstract $statement): int {
        $logId = $this->logStatement($statement, $statement->getParams());
        $result = $this->prepareStatement($statement)->rowCount();
        $this->logStatementUpdate($logId, $result);
        return $result;
    }

    public function queryGetInsertId(StatementAbstract $statement): int|string {
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
        $result = self::checkInsertStatement($statement) ? $conn->lastInsertId() : $stmt->rowCount();
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
        return ! $this->connection->inTransaction() || $this->connection->rollBack();
    }

    /**
     * 本方法为解决连接超时后导致Pdo::prepare方法异常却不能正常抛出的问题，经过多重尝试（包括修复相关BUG、优化代码、Barrier换WaitGroup等）均未解决，因此怀疑是Swoole或PDO问题。
     * 因未找到相关问题及解决方法，故自创此“超时自动强制退出协程”的方法解决该问题，若日后找到真正原因或组件厂商解决了此问题，则应删除此方法及调用
     * @param int $coroutineId
     * @param bool $isRegister 是否为注册自动超时
     * @throws QueryException
     */
    public static function registerCoroutineAutoReleaseOrHandle(int $coroutineId, bool $isRegister = true, string $type = 'db'): void {
        if ($coroutineId < 1) return;
        static $maxPrepareTime = 1200;
        static $coroutineMapping = [];

        if ($isRegister) {
            if (key_exists($coroutineId, $coroutineMapping[$type] ?? [])) {
                unset($coroutineMapping[$type][$coroutineId]);
                return; // 若查询较快，记录cid时该协程已退出，则不再继续记录
            }
            $coroutineMapping[$type][$coroutineId] = Timer::after($maxPrepareTime, function() use($coroutineId, & $coroutineMapping, $type) {
                // 如果协程还存在，则表示纯粹超时了，则需手动取消协程，取消动作实际会从IO切换恢复到后续过程
                if ($before = Coroutine::exists($coroutineId))
                    Coroutine::cancel($coroutineId);
                else // 如果不存在了，则表示可能抛出了异常，被捕获后退出了协程，此时需解除映射
                    unset($coroutineMapping[$type][$coroutineId]);
                testPoint("超时了！！！", $before, Coroutine::exists($coroutineId));
            });
        } else if ($timerId = $coroutineMapping[$type][$coroutineId] ?? 0) {  // 以非注册逻辑进入时，映射必未解除，此处必为类真值，表达式仅为取timerId
            unset($coroutineMapping[$type][$coroutineId]);
            // 若协程被取消，则表示prepare超时了，需抛出超时异常，否则需清除计时器
            Coroutine::isCanceled() ? throw new QueryException(QueryException::PDO_PREPARE_TIMEOUT) : Timer::clear($timerId);
        } else {
            $coroutineMapping[$type][$coroutineId] = 0; // 未注册即进入此逻辑则表示查询较快，记录此协程id以便后续不进入注册逻辑
        }
    }
}
