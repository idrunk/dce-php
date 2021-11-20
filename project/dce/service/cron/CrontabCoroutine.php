<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/10/23 11:00
 */

namespace dce\service\cron;

use Swoole\Coroutine\System;

class CrontabCoroutine extends Crontab {
    public function run(Cron|string $task, int $now): void {
        go(fn() => parent::run($task, $now));
    }

    /** @inheritDoc */
    protected static function exec(string $command): array {
        return System::exec($command);
    }

    protected static function sleep(): void {
        System::sleep(self::getIntervalSeconds());
    }
}