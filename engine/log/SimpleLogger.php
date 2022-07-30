<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2022/7/26 0:06
 */

namespace dce\log;

use dce\base\LoggerType;

class SimpleLogger extends MatrixLogger {
    /** @inheritDoc */
    public function push(LoggerType $type, string $topic, string|null $content = null): void {
        $config = $type->config();
        $topic = self::applyTime($topic, time());
        $config['console'] && LogManager::console($content ? "$topic\n" . self::slim($content) : "$topic", prefix: '');
        ($logFile = LogManager::standardConfigLogfile($config)) && LogManager::write($logFile, $content ? "$topic\n$content" : $topic);
    }
}