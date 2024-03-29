<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/09/17 15:41
 */

namespace dce\db\proxy;

use dce\db\connector\DbConnector;

abstract class Transaction {
    protected static int $timeToExpire = 60;

    /** @var int 执行回收操作的百分率 */
    protected static int $percentageCollect = 10;

    /** @var static[] $pond */
    protected static array $pond = [];

    /** @var int 进入次数/嵌套数 */
    protected int $entries = 0;

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

    /** 清除绑定的属性 */
    public function clearBounds(): void {
        unset($this->connector);
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
        // warning: Empty transaction
        if (! isset($this->connector)) return false;
        return $this->connector->commit();
    }

    /**
     * 回滚连接事务
     * @return bool
     */
    private function connectorRollback(): bool {
        // warning: Empty transaction
        if (! isset($this->connector)) return false;
        return $this->connector->rollback();
    }

    /**
     * 提交事务
     */
    public function commit(): bool {
        // 提交时必须跑到最外层时才行
        if (-- $this->entries > 0) return false;
        $result = $this->connectorCommit();
        $this->removeInstance();
        return $result;
    }

    /**
     * 回滚事务
     */
    public function rollback(): bool {
        // 回滚时任何层次都可
        $result = $this->connectorRollback();
        $this->removeInstance();
        return $result;
    }

    /**
     * 回滚超时的事务
     */
    protected function clearExpired(): void {
        $minEffectiveStamp = time() - self::$timeToExpire;
        foreach (self::$pond as $transaction)
            $transaction->createStamp < $minEffectiveStamp && $transaction->rollback();
    }

    /**
     * 环境有效性校验
     */
    abstract protected function envValid(): void;
}