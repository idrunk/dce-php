<?php
/**
 * Author: Drunk
 * Date: 2020-01-16 18:48
 */

namespace dce\db\connector;

abstract class ScriptLogger {
    /** @var static[] */
    private static array $drivers = [];

    public static function addDriver(self $driver): void {
        self::$drivers[] = $driver;
    }

    private static function genUniqueId(): string {
        return (microtime(1) * 10000 << 14) + mt_rand(0, 16383);
    }

    public static function trigger(mixed ... $args): string {
        $logId = self::genUniqueId();
        foreach (self::$drivers as $driver) {
            $driver->log($logId, ... $args);
        }
        return $logId;
    }

    public static function triggerUpdate(string $logId, mixed ... $args): void {
        foreach (self::$drivers as $driver) {
            $driver->logUpdate($logId, ... $args);
        }
    }

    abstract public function log(string $logId, mixed ... $args): void;

    abstract public function logUpdate(string $logId, mixed ... $args): void;
}
