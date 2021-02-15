<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/09/17 15:41
 */

namespace dce\db\proxy;

use dce\db\connector\DbConnector;

abstract class Transaction {
    protected static int $time_to_expire = 60;

    /** @var static[] $pond */
    protected static array $pond = [];

    protected int $createStamp;

    protected DbConnector $connector;

    public function __construct() {
        $this->createStamp = time();
        $this->envValid();
        $this->pushInstance();
    }

    /**
     * 推入事务实例到队列
     */
    private function pushInstance(): void {
        self::$pond[] = $this;
    }

    /**
     * 从队列移除事务实例
     * @return bool
     */
    private function removeInstance(): bool {
        $index = array_search($this, self::$pond);
        if (false !== $index) {
            array_splice(self::$pond, $index, 1);
            return true;
        }
        return false;
    }

    /**
     * 标记数据库连接
     * @param DbConnector $connector
     * @return $this
     */
    protected function markConnector(DbConnector $connector): self {
        $this->connector = $connector;
        return $this;
    }

    /**
     * 取连接实例
     * @return DbConnector
     */
    public function getConnector(): DbConnector {
        return $this->connector;
    }

    /**
     * 提交连接事务
     * @return bool
     */
    private function connectorCommit(): bool {
        if (! isset($this->connector)) {
            // warning: Empty transaction
            return false;
        }
        return $this->connector->commit();
    }

    /**
     * 回滚连接事务
     * @return bool
     */
    private function connectorRollback(): bool {
        if (! isset($this->connector)) {
            // warning: Empty transaction
            return false;
        }
        return $this->connector->rollback();
    }

    /**
     * 提交事务
     */
    public function commit(): bool {
        $result = $this->connectorCommit();
        $this->removeInstance();
        return $result;
    }

    /**
     * 回滚事务
     */
    public function rollback(): bool {
        $result = $this->connectorRollback();
        $this->removeInstance();
        return $result;
    }

    /**
     * 回滚超时的事务
     */
    protected function clearExpired(): void {
        $minEffectiveStamp = time() - self::$time_to_expire;
        foreach (self::$pond as $transaction) {
            if ($transaction->createStamp < $minEffectiveStamp) {
                $transaction->rollback();
            }
        }
    }

    /**
     * 环境有效性校验
     */
    abstract protected function envValid(): void;
}