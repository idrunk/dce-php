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
    public const ALIAS_NO_SHARDING = 'no_sharding';

    /** @var int 使用次数，事务中已经发起的查询数 */
    public int $uses = 0;

    private int $requestId;

    private string $dbAlias;

    private DbPool $pool;

    private const CLEAR_TRIGGER_SECOND = 10;

    private static int $lastBegin = PHP_INT_MAX >> 8;

    private static int $lastCommit = 0;

    private function __construct(
        private string $shardingAlias,
    ) {
        parent::__construct();
        $this->requestId = RequestManager::currentId();
        // 如果提交过，最后事务开始10秒后还没完成，或前个事务10秒还没提交，则进入自动清除过期事务流程
        (self::$lastCommit ? self::$lastBegin - self::$lastCommit : $this->createStamp - self::$lastBegin)
            > self::CLEAR_TRIGGER_SECOND && $this->clearExpired();
        self::$lastBegin = $this->createStamp;
    }

    /** @inheritDoc */
    protected function envValid(): void {
        ! SwooleUtility::inCoroutine() && throw new TransactionException(TransactionException::NEED_RUN_IN_COROUTINE);
    }

    /** @inheritDoc */
    public function commit(): bool {
        $result = parent::commit();
        self::$lastCommit = time();
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

    public static function rollbackRequestThrown(int $requestId): void {
        if (SwooleUtility::inCoroutine()) foreach (self::$pond as $trans) $trans->requestId === $requestId && $trans->rollback();
    }

    /**
     * 实例化一个事务对象，并递增进入计数
     * @param string $shardingAlias
     * @return static
     */
    public static function begin(string $shardingAlias): self {
        $instance = self::aliasMatch($shardingAlias) ?? new self($shardingAlias);
        $instance->entries ++;
        return $instance;
    }

    /**
     * 尝试开启事务
     * @param string $shardingAlias
     * @param string $dbAlias
     * @param DbPool $dbPool
     * @return self|DbConnector
     * @throws TransactionException
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