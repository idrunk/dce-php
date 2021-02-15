<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-04 12:05
 */

namespace dce\db\connector;

class ScriptLoggerConsole extends ScriptLogger {
    public function log(string $logId, mixed ... $args): void {
        [[$host, $port, $dbName], $sql] = $args;
        echo sprintf("[%s]  db: %s  sql: %s\n\n", date('H:i:s'), "{$host}:{$port}/{$dbName}", $sql);
    }

    public function logUpdate(string $logId, mixed ... $args): void {}
}
