<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/7/11 11:36
 */

namespace dce\db\connector;

use dce\Dce;
use dce\pool\PoolException;
use dce\pool\PoolProductionConfig;
use dce\pool\Pool;
use dce\sharding\middleware\ShardingTransaction;
use drunk\Structure;
use PDOException;
use Throwable;

class DbPool extends Pool {
    private const PdoDisconnectedCodes = [2006];

    private string $dbAlias;

    protected function produce(PoolProductionConfig $config): DbConnector {
        $connector = new PdoDbConnector();
        $connector->connect($config->dbName, $config->host, $config->dbUser, $config->dbPassword, $config->dbPort, false);
        return $connector;
    }

    public function fetch(): DbConnector {
        return $this->get();
    }

    /**
     * @param string ...$identities<string $dbAlias, bool $isWrite>
     * @return static
     */
    public static function inst(string ... $identities): static {
        $inst = parent::getInstance(DbPoolProductionConfig::class, ... $identities);
        $inst->dbAlias ??= $identities[0];
        return $inst;
    }

    protected function retryable(Throwable $throwable): Throwable|bool {
        // 是否连接丢失的异常
        $result = $throwable instanceof PDOException && in_array($throwable->errorInfo[1] ?? 0, self::PdoDisconnectedCodes);

        // 若当前库开启了分库事务，且事务中成功发送过请求，则禁止重试连接
        if ($result && false !== Structure::arraySearch(function($shardingAlias) {
                $transaction = ShardingTransaction::aliasMatch($shardingAlias);
                $transaction && $transaction->clearBounds();
                return $transaction->uses ?? 0 > 1;
            }, array_unique(array_column(isset(Dce::$config->sharding)
                ? Dce::$config->sharding->filter(fn($c) => key_exists($this->dbAlias, $c->mapping)) : [], 'alias')))
        ) $result = new PoolException(PoolException::DISCONNECTED_TRANSACTION_ACTIVATED, previous: $throwable);

        return $result;
    }
}
