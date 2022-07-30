<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2022/7/26 0:05
 */

namespace dce\log;

use dce\base\LoggerType;
use dce\base\SwooleUtility;
use dce\Dce;
use Swoole\Table;
use Swoole\Timer;

class TableLogger extends MatrixLogger {
    private Table $table;

    public function __construct() {
        SwooleUtility::rootProcessConstraint();
        $this->table = new Table(2048);
        $this->table->column('type', Table::TYPE_INT, 1);
        $this->table->column('content', Table::TYPE_STRING, 65535);
        $this->table->create();
    }

    /** @inheritDoc */
    public function push(LoggerType $type, string $topic, string|null $content = null): void {
        $c = $type->spec();
        $now = floor(microtime(true) * 1000);
        $key = $this->table->count() + 1;
        $this->table->set("$key", ['type' => $type->value, 'content' => self::pack(self::applyTime($topic, floor($now / 1000)), $content)]);
        $logLast = Dce::$cache->shm->get('log-last');
        Dce::$cache->shm->set('log-last', $now);
        if ($logLast) {
            if ($now - $c['timeout'] >= (Dce::$cache->shm->get('log-from') ?: $now)) {
                Dce::$cache->shm->set('log-from', $now);
                $this->delay($type);
            } else if ($key >= $c['capital']) {
                $this->dumpAndClean();
            }
        } else {
            Dce::$cache->shm->set('log-from', $now);
            $this->delay($type);
        }
    }

    private static function pack(string $topic, string|null|false $content = null): string|array {
        if ($content === false) {
            return str_contains($topic, '[#-=-;]') ? explode('[#-=-;]', $topic) : [$topic, ''];
        } else {
            return $topic . ($content === null ? '' : "[#-=-;]$content");
        }
    }

    private function delay(LoggerType $type): void {
        Timer::after($type->spec()['delay'], function(LoggerType $type) {
            $c = $type->spec();
            $now = floor(microtime(true) * 1000);
            $logFrom = Dce::$cache->shm->get('log-from');
            $logLast = Dce::$cache->shm->get('log-last');
            (($isTimeout = $now - $logFrom >= $c['timeout']) || (($delay = $now - $logLast) >= $c['delay'] && $delay <= $c['delay'] * 2)) && $this->dumpAndClean();
            ! $isTimeout && $this->delay($type);
        }, $type);
    }

    public function dumpAndClean(): void {
        $keys = $logs = [];
        foreach ($this->table as $k => $log) {
            $keys[] = $k;
            $logs[] = $log;
        }
        if (! $keys) return;
        foreach ($keys as $key) $this->table->delete($key);
        $consoleLogs = '';
        $fileLogs = [];
        foreach ($logs as $log) {
            $type = LoggerType::from($log['type']);
            $config = $type->config();
            [$topic, $content] = self::pack($log['content'], false);
            $config['console'] && $consoleLogs .= $content ? "$topic\n" . self::slim($content) . "\n" : "$topic\n";
            if ($logFile = LogManager::standardConfigLogfile($config)) {
                ! key_exists($logFile, $fileLogs) && $fileLogs[$logFile] = '';
                $fileLogs[$logFile] .= $content ? "$topic\n$content\n" : "$topic\n";
            }
        }
        LogManager::console(rtrim($consoleLogs), prefix: '');
        foreach ($fileLogs as $file => $content) LogManager::write($file, rtrim($content));
    }
}