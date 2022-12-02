<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2022/7/25 23:00
 */

namespace dce\log;

use dce\base\LoggerType;
use dce\loader\attr\Singleton;

abstract class MatrixLogger {
    public static function applyTime(string $topic, int $time): string {
        return str_replace('[#T;]', '[' . date('d H:i:s', $time) . ']', $topic);
    }

    protected static function slim(string $content, int $targetLength = 1024): string {
        return mb_substr($content, 0, $targetLength) . (mb_strlen($content) > $targetLength ? '...' : '');
    }

    /**
     * @param LoggerType $type
     * @param string $topic
     * @param string|null $content
     */
    abstract public function push(LoggerType $type, string $topic, string|null $content = null): void;

    public static function inst(): static {
        return Singleton::gen(static::class);
    }
}
