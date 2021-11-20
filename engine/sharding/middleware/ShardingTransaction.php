<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020-09-19 14:27
 */

namespace dce\sharding\middleware;

use dce\base\SwooleUtility;
use dce\db\connector\DbConnector;
use dce\db\connector\DbPool;
use dce\db\proxy\Transaction;
use dce\db\proxy\TransactionException;
use dce\project\request\RequestManager;

class ShardingTransaction extends Transaction {
    public const NO_SHARDING_ALIAS = 'no_sharding';

    /** @var int 使用次数，事务中已经发起得查询数 */
    public int $uses = 0;

    private int $requestId;

    private string $dbAlias;

    private DbPool $pool;

    public function __construct(
        private string $shardingAlias,
    ) {
        parent::__construct();
        $this->requestId = RequestManager::currentId();
        $this->clearExpired();
    }

    /** @inheritDoc */
    protected function envValid(): void {
        if (! SwooleUtility::inCoroutine()) {
            throw new TransactionException(TransactionException::NEED_RUN_IN_COROUTINE);
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
     * 按请求ID与库名匹配事务实例
     * @param string $shardingAlias
     * @return static|null
     */
    public static function aliasMatch(string $shardingAlias): self|null {
        $requestId = RequestManager::currentId();
        foreach (self::$pond as $transaction)
            if ($transaction->requestId === $requestId && $transaction->shardingAlias === $shardingAlias)
                return $transaction;
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
                    throw new TransactionException(TransactionException::NOT_SUPPORT_SHARDING_TRANSACTION);
                }
            } else {
                $connector = $dbPool->fetch();
                $transaction->markConnector($connector);
                $transaction->pool = $dbPool;
                $transaction->dbAlias = $dbAlias;
                $connector->begin();
            }
            $transaction->uses ++;
            return $transaction;
        } else {
            return $dbPool->fetch();
        }
    }
}