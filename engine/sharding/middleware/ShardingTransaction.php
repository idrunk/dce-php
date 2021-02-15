<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020-09-19 14:27
 */

namespace dce\sharding\middleware;

use Co;
use dce\base\SwooleUtility;
use dce\db\connector\DbConnector;
use dce\db\connector\DbPool;
use dce\db\proxy\Transaction;
use dce\db\proxy\TransactionException;

class ShardingTransaction extends Transaction {
    public const NO_SHARDING_ALIAS = 'no_sharding';

    private int $requestId;

    private string $dbAlias;

    private DbPool $pool;

    public function __construct(
        private string $shardingAlias,
    ) {
        parent::__construct();
        $this->requestId = self::genRequestId();
        // mark 这玩意儿大概单独做成延时任务比较好
        $this->clearExpired();
    }

    /** @inheritDoc */
    protected function envValid(): void {
        if (! SwooleUtility::inCoroutine()) {
            throw new TransactionException('TransactionSharding仅支持协程环境');
        }
    }

    /** @inheritDoc */
    public function commit(): bool {
        $result = parent::commit();
        $this->pool->put($this->connector);
        return $result;
    }

    /** @inheritDoc */
    public function rollback(): bool {
        $result = parent::rollback();
        $this->pool->put($this->connector);
        return $result;
    }

    /**
     * 取请求ID
     * @return int
     */
    private static function genRequestId(): int {
        $requestId = Co::getCid();
        while (($pcid = Co::getPcid($requestId)) > 0) {
            $requestId = $pcid;
        }
        return $requestId;
    }

    /**
     * 按请求ID与库名匹配事务实例
     * @param string $shardingAlias
     * @return static|null
     */
    private static function aliasMatch(string $shardingAlias): self|null {
        $requestId = self::genRequestId();
        foreach (self::$pond as $transaction) {
            if ($transaction->requestId === $requestId && $transaction->shardingAlias === $shardingAlias) {
                return $transaction;
            }
        }
        return null;
    }

    /**
     * 尝试开启事务
     * @param string $shardingAlias
     * @param string $dbAlias
     * @param DbPool $dbPool
     * @return self|DbConnector
     */
    public static function tryBegin(string $shardingAlias, string $dbAlias, DbPool $dbPool): self|DbConnector {
        $transaction = self::aliasMatch($shardingAlias);
        if ($transaction) {
            if (isset($transaction->connector)) {
                if ($transaction->dbAlias !== $dbAlias) {
                    $transaction->rollback();
                    throw new TransactionException('不支持跨分库事务');
                }
            } else {
                $connector = $dbPool->fetch();
                $transaction->markConnector($connector);
                $transaction->pool = $dbPool;
                $transaction->dbAlias = $dbAlias;
                $connector->begin();
            }
            return $transaction;
        } else {
            return $dbPool->fetch();
        }
    }
}