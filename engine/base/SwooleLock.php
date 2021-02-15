<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020-12-27 21:03
 */

namespace dce\base;

class SwooleLock extends Lock {
    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function procLockInit(): void {
        SwooleUtility::processLockInit();
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function procLock(string $identification, int $maximum = 1): bool {
        return SwooleUtility::processLock($identification, $maximum);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function procUnlock(string $identification): void {
        SwooleUtility::processUnlock($identification);
    }

    /** @inheritDoc */
    public function coLock(string $identification, int $maximum = 1): bool {
        return SwooleUtility::coroutineLock($identification, $maximum);
    }

    /** @inheritDoc */
    public function coUnlock(string $identification): void {
        SwooleUtility::coroutineUnlock($identification);
    }
}