<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/09/17 15:53
 */

namespace dce\db\proxy;

use dce\base\SwooleUtility;
use dce\db\connector\DbConnector;

class SimpleTransaction extends Transaction {
    public function __construct(
        private SimpleDbProxy $proxy
    ) {
        parent::__construct();
    }

    /** @inheritDoc */
    protected function envValid(): void {
        if (SwooleUtility::inCoroutine()) {
            throw new TransactionException(TransactionException::CANNOT_RUN_IN_COROUTINE);
        }
        if (self::proxyMatch($this->proxy)) {
            throw new TransactionException(TransactionException::REPEATED_OPEN);
        }
    }

    /**
     * 按代理匹配事务实例
     * @param DbProxy $proxy
     * @return static|null
     */
    private static function proxyMatch(DbProxy $proxy): static|null {
        foreach (self::$pond as $transaction) {
            if ($transaction->proxy === $proxy) {
                return $transaction;
            }
        }
        return null;
    }

    /**
     * 检测并尝试开启连接事务
     * @param SimpleDbProxy $proxy
     * @param DbConnector $connector
     */
    public static function tryBegin(SimpleDbProxy $proxy, DbConnector $connector): void {
        $transaction = self::proxyMatch($proxy);
        if ($transaction && ! isset($transaction->connector)) {
            $transaction->markConnector($connector);
            $connector->begin();
        }
    }
}