<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/26 1:41
 */

namespace dce\service\server;

use dce\project\request\SessionManager;

class SessionManagerNoop extends SessionManager {
    /** @inheritDoc */
    protected function logFdMid(string $sid, int $mid, int $fd, string $apiHost, int $apiPort, string $extra = ''): void {}

    /** @inheritDoc */
    protected function updateLog(int $id, array $data): void {}

    /** @inheritDoc */
    public function unLog(int $id): void {}

    /** @inheritDoc */
    public function filterByMid(int $mid): array {
        return [];
    }

    /** @inheritDoc */
    public function filterBySid(string $sid): array {
        return [];
    }

    /** @inheritDoc */
    public function filterByFd(int $fd, string $apiHost, int $apiPort, string $extra = ''): array {
        return [];
    }

    /** @inheritDoc */
    public function updateSession(int $mid, string $key, mixed $value): void {}

    /** @inheritDoc */
    public function destroySession(int $mid): void {}

    /** @inheritDoc */
    public function expiredCollection(): void {}
}
