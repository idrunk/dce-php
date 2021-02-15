<?php
/**
 * Author: Drunk
 * Date: 2020-01-17 17:44
 */

namespace dce\db\connector;

use Redis;

class ScriptLoggerRedis extends ScriptLogger {
    private const keyListKey = 'logKeys';

    private Redis $redis;

    public function __construct(Redis $redis) {
        $this->redis = $redis;
    }

    public function log(string $logId, mixed ... $args): void {
        [$db, $sql] = $args;
        $this->redis->lPush(self::keyListKey, $logId);
        $this->redis->hMSet($logId, [
            'db' => $db,
            'request_time' => 10000 * microtime(1),
            'request_sql' => $sql,
        ]);
    }

    public function logUpdate(string $logId, mixed ... $args): void {
        [$responseResult] = $args;
        $this->redis->hMSet($logId, [
            'response_time' => 10000 * microtime(1),
            'response_result' => $responseResult,
        ]);
    }
}
