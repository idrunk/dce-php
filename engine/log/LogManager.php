<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-04 12:11
 */

namespace dce\log;

use dce\db\connector\ScriptLogger;
use dce\db\connector\ScriptLoggerConsole;
use dce\Dce;
use dce\project\request\RawRequest;
use dce\project\request\RawRequestCli;
use dce\project\request\Request;
use dce\project\request\RequestManager;
use dce\service\server\Connection;
use dce\service\server\ServerMatrix;
use dce\service\cron\Cron;
use Stringable;
use Throwable;
use websocket\service\WebsocketServer;
use Swoole\Coroutine\Server\Connection as SwooleConnection;

final class LogManager {
    public static function init() {
        if (Dce::$config->log['db']['console']) {
            ScriptLogger::addDriver(new ScriptLoggerConsole());
        }
    }

    public static function warning(Throwable $throwable): void {
        self::exception($throwable, true, true);
    }

    /**
     * 打印记录异常
     * @param Throwable $throwable
     * @param bool $isSimple
     * @param bool $isWarn
     */
    public static function exception(Throwable $throwable, bool $isSimple, bool $isWarn = false): void {
        $pureContent = self::exceptionRender($throwable, $isSimple, warn: $isWarn);

        // 打印异常
        self::consoleFileLog($pureContent, Dce::$config->log['exception']['console'], self::standardConfigLogfile(Dce::$config->log['exception']));
    }

    /**
     * 渲染异常
     * @param Throwable $throwable
     * @param bool|null $simple {null: 渲染为通用响应结构体, true: 渲染为简介提示, false: 渲染为详细异常}
     * @param bool $html 是否用html包裹
     * @param bool $warn
     * @return string
     */
    public static function exceptionRender(Throwable $throwable, bool|null $simple = false, bool $html = false, bool $warn = false): string {
        $now = date('Y-m-d H:i:s');
        if ($simple === null) {
            $data = ['status' => false];
            $throwable->getCode() && $data['code'] = $throwable->getCode();
            $throwable->getMessage() && $data['message'] = $throwable->getMessage();
            $content = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else if ($warn) {
            $content = sprintf('[%s] (警告：%s) %s', $now, $throwable->getCode(), $throwable->getMessage());
        } else {
            $content = $simple ? sprintf('[%s] (%s: %s) %s', $now, get_class($throwable), $throwable->getCode(), $throwable->getMessage())
                : sprintf("[%s] (%s: %s) %s\n%s", $now, get_class($throwable),$throwable->getCode(), $throwable->getMessage(), $throwable);
            $html && $content = sprintf('<!doctype html><html lang="zh"><head><meta charset="UTF-8"><title>%s</title></head><body><pre>%s</pre></body></html>', $throwable->getMessage(), $content);
        }
        return $content;
    }

    /**
     * 打印请求日志
     * @param Request $request
     */
    public static function request(Request $request): void {
        // 所有被调用频率较高的日志，都尝试先拦截，节省脚本执行量
        if (! (Dce::$config->log['access']['request'] || Dce::$config->log['access']['logfile_power']) || ($rawRequest = $request->rawRequest) instanceof RawRequestCli) return;

        $topic = sprintf('[%s] (%s %s) %s/%s', date('Y-m-d H:i:s'), ($rawRequest->isConnecting ?? false) ? '连' : '求',
            $rawRequest->getClientInfo()['request'], $rawRequest->getClientInfo()['ip'], $request->session->getId() ?? '');
        self::consoleFileLog($topic, Dce::$config->log['access']['request'], self::standardConfigLogfile(Dce::$config->log['access']),
            is_string($rawRequest->getRawData()) ? $rawRequest->getRawData() : json_encode($rawRequest->getRawData(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * 打印响应日志
     * @param RawRequest $rawRequest
     * @param mixed $data
     */
    public static function response(RawRequest $rawRequest, mixed $data): void {
        if (! Dce::$config->log['access']['response'] && ! Dce::$config->log['access']['logfile_power']) return;

        $topic = sprintf('[%s] (应 %s) %s/%s', date('Y-m-d H:i:s'), $rawRequest->getClientInfo()['request'],
            $rawRequest->getClientInfo()['ip'], RequestManager::current()->session->getId() ?? '');
        self::consoleFileLog($topic, Dce::$config->log['access']['response'], self::standardConfigLogfile(Dce::$config->log['access']),
            is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 打印连接日志
     * @param Connection $conn
     * @param bool $isConnect
     */
    public static function connect(Connection $conn, bool $isConnect = true): void {
        if (! Dce::$config->log['access']['connect'] && ! Dce::$config->log['access']['logfile_power']) return;

        $topic = sprintf('[%s] (%s %s) %s/%s', date('Y-m-d H:i:s'), $isConnect ? '连' : '断',
            $conn->server instanceof WebsocketServer ? 'websocket' : 'tcp',
            $conn->server->getServer()->getClientInfo($conn->fd)['remote_ip'] ?? '', $conn->session->getId());
        self::consoleFileLog($topic, Dce::$config->log['access']['connect'], self::standardConfigLogfile(Dce::$config->log['access']));
    }

    /**
     * 打印消息推送日志
     * @param ServerMatrix $server
     * @param int|string $fd
     * @param string $data
     * @param string $path
     */
    public static function send(ServerMatrix $server, int|string $fd, string $data, string $path): void {
        // 仅当未取到当前请求时才进入send日志逻辑，因为请求已经被response日志处理了
        if (($conn = Connection::exists($fd))?->onRequest() || ! (Dce::$config->log['access']['send'] || Dce::$config->log['access']['logfile_power'])) return;

        $isUdp = ! is_int($fd);
        $type = $isUdp ? 'udp' : ($server instanceof WebsocketServer ? 'websocket' : 'tcp');
        if ($isUdp) {
            $ip = $fd;
            $sid = ''; // udp未自动开启session，可能没有sid
        } else {
            $ip = $server->getServer()->getClientInfo($fd)['remote_ip'] ?? '';
            $sid = $conn?->session->getId();
        }
        self::consoleFileLog(sprintf('[%s] (发 %s %s) %s/%s', date('Y-m-d H:i:s'), $type, $path ?: '', $ip, $sid),
            Dce::$config->log['access']['send'], self::standardConfigLogfile(Dce::$config->log['access']), $data);
    }

    public static function rpcConnect($serverHost, $serverPort, $clientHost, bool $isConnect = true): void {
        if (! Dce::$config->log['rpc']['connect'] && ! Dce::$config->log['rpc']['logfile_power']) return;

        self::consoleFileLog(sprintf('[%s] (RPC%s %s) %s', date('Y-m-d H:i:s'), $isConnect ? '连' : '断', "$serverHost:$serverPort", $clientHost),
            Dce::$config->log['rpc']['connect'], self::standardConfigLogfile(Dce::$config->log['rpc']));
    }

    public static function rpcRequest(string $method, array $arguments, string $clientIp): void {
        if (! Dce::$config->log['rpc']['request'] && ! Dce::$config->log['rpc']['logfile_power']) return;

        self::consoleFileLog(sprintf('[%s] (RPC求 %s) %s', date('Y-m-d H:i:s'), $method, $clientIp), Dce::$config->log['rpc']['request'],
            self::standardConfigLogfile(Dce::$config->log['rpc']), implode("\n", array_map(fn($a) => is_scalar($a) ? $a : json_encode($a, JSON_UNESCAPED_UNICODE), $arguments)));
    }

    public static function rpcResponse(string $method, mixed $result, string $clientIp): void {
        if (! Dce::$config->log['rpc']['response'] && ! Dce::$config->log['rpc']['logfile_power']) return;

        self::consoleFileLog(sprintf('[%s] (RPC应 %s) %s', date('Y-m-d H:i:s'), $method, $clientIp), Dce::$config->log['rpc']['response'],
            self::standardConfigLogfile(Dce::$config->log['rpc']), is_scalar($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param Cron $task
     * @param Cron[] $tasks
     */
    public static function cron(Cron $task, array $tasks): void {
        $topic = sprintf('[%s] (开始) %s', date('Y-m-d H:i:s'), $task->command);

        self::consoleFileLog($topic, Dce::$config->log['cron']['console'], self::standardConfigLogfile(Dce::$config->log['cron']));

        ($tasksFile = self::standardConfigLogfile(Dce::$config->log['cron'], 'tasks'))
            && file_put_contents($tasksFile, array_reduce($tasks, fn($c, $t) => $c . ($c ? '\n' : '') . $t->format(), ''));
    }

    public static function cronDone(Cron $task, string $output): void {
        $topic = sprintf('%s[%s] (完成) %s', ">>>\n$output\n<<<\n\n", date('Y-m-d H:i:s'), $task->command);

        self::consoleFileLog($topic, Dce::$config->log['cron']['console'], self::standardConfigLogfile(Dce::$config->log['cron']));
    }

    public static function showCron(bool $showStatus): string {
        return substr(file_get_contents(self::standardConfigLogfile(Dce::$config->log['cron'], $showStatus ? 'tasks' : '')), - 16384);
    }

    public static function dce(Stringable|string $text): void {
        self::consoleFileLog(sprintf('[%s] %s', date('Y-m-d H:i:s'), $text),
            Dce::$config->log['dce']['console'], self::standardConfigLogfile(Dce::$config->log['dce']));
    }

    private static function consoleFileLog(string $topic, bool $console = true, string $logFile = null, string $content = null): void {
        if ($content) {
            $topic .= "\n" . self::contentFormat($content);
            $content = "$topic\n$content";
        } else {
            $content = $topic;
        }
        $console && self::console($topic, prefix: '');
        if ($logFile) {
            ! file_exists(dirname($logFile)) && mkdir(dirname($logFile), 0755, true);
            file_put_contents($logFile, "$content\n\n", FILE_APPEND | LOCK_EX);
        }
    }

    private static function contentFormat(string $content, bool $short = true): string {
        $max = $short ? 1024 : 16384;
        return mb_substr($content, 0, $max) . (mb_strlen($content) > $max ? '...' : '');
    }

    private static function standardConfigLogfile(array $config, string $replacement = null): string {
        return $config['logfile_power'] ? sprintf($config['logfile'], $replacement ?: date($config['logfile_format'])) : '';
    }

    public static function console(Stringable|string $text, string $suffix = "\n\n", string $prefix = null): void {
        $prefix ??= sprintf('[%s] ', date('Y-m-d H:i:s'));
        DCE_CLI_MODE && print("$prefix$text$suffix");
    }
}