<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/10/23 11:00
 */

namespace dce\service\cron;

class CrontabBasic extends Crontab {
    /** @inheritDoc */
    protected static function exec(string $command): array {
        exec($command, $output, $code);
        return ['output' => implode("\n", $output), 'code' => $code];
    }

    protected static function sleep(): void {
        sleep(self::getIntervalSeconds());
    }
}