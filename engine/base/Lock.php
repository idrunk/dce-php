<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020-12-27 02:44
 */

namespace dce\base;

use dce\Dce;

class Lock {
    public function __construct() {
        $this->procLockInit();
        $this->distributedLockInit();
    }

    /**
     * 跨进程协程锁初始化 (实例化跨进程对象)
     */
    protected function procLockInit(): void {}

    /**
     * 加跨进程协程锁 (悲观, 自旋, 不可重入)
     * @param string $identification 锁标识
     * @param int $maximum 跨进程可重入次数
     * @return bool
     */
    public function procLock(string $identification, int $maximum = 1): bool {
        return true;
    }

    /**
     * 解跨进程协程锁
     * @param string $identification
     */
    public function procUnlock(string $identification): void {}

    /**
     * 加协程锁 (悲观, 自旋, 不可重入)
     * @param string $identification 锁标识
     * @param int $maximum 跨进程可重入次数
     * @return bool
     */
    public function coLock(string $identification, int $maximum = 1): bool {
        return true;
    }

    /**
     * 解协程锁
     * @param string $identification
     */
    public function coUnlock(string $identification): void {}

    /**
     * 分布式锁初始化
     */
    protected function distributedLockInit(): void {}

    /**
     * 分布式锁加锁
     * @return bool
     */
    public function distributedLock(): bool {
        return true;
    }

    /**
     * 分布式锁解锁
     */
    public function distributedUnlock(): void {}

    /**
     * 实例化一个并发锁对象
     * @return static
     */
    public static function init(): static {
        $lockClass = Dce::$config->lockClass ?? (SwooleUtility::inSwoole() ? SwooleLock::class : self::class);
        return new $lockClass;
    }
}