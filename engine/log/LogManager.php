<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-04 12:11
 */

namespace dce\log;

use dce\base\LoggerType;
use dce\base\SwooleUtility;
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

final class LogManager {
    private static MatrixLogger $simpleLogger;
    private static MatrixLogger $tableLogger;
    private static bool $isServerStart;

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
        self::push(LoggerType::Exception, "\n" . $pureContent);
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
        if ($simple === null) {
            $data = ['status' => false];
            $throwable->getCode() && $data['code'] = $throwable->getCode();
            $throwable->getMessage() && $data['message'] = $throwable->getMessage();
            $content = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else if ($warn) {
            $content = sprintf('[#T;] (警告：%s) %s', $throwable->getCode(), $throwable->getMessage());
        } else {
            $content = $simple ? sprintf('[#T;] (%s: %s) %s', get_class($throwable), $throwable->getCode(), $throwable->getMessage())
                : sprintf("[#T;] (%s: %s) %s\n%s", get_class($throwable),$throwable->getCode(), $throwable->getMessage(), $throwable);
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

        $isConnecting = $rawRequest->isConnecting ?? false;
        $topic = sprintf("\n[#T;] (%s %s) %s/%s%s", $isConnecting ? '连' : '求',
            $rawRequest->getClientInfo()['request'], $rawRequest->getClientInfo()['ip'], $request->session->getId() ?? '', $isConnecting ? '/' . $request->fd : '');
        self::push(LoggerType::Request, $topic, is_string($data = $rawRequest->getRawData()) ? (($request->files ?? false) ? '(upload content)' : $data) : json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 打印响应日志
     * @param RawRequest $rawRequest
     * @param mixed $data
     */
    public static function response(RawRequest $rawRequest, mixed $data): void {
        if (! Dce::$config->log['access']['response'] && ! Dce::$config->log['access']['logfile_power']) return;

        $request = RequestManager::current();
        $topic = sprintf('[#T;] (应 %s) %s/%s', $rawRequest->getClientInfo()['request'],
            $rawRequest->getClientInfo()['ip'], isset($request->session) ? $request->session->getId() : '');
        self::push(LoggerType::Response, $topic, is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 打印连接日志
     * @param Connection $conn
     * @param bool $isConnect
     */
    public static function connect(Connection $conn, bool $isConnect = true): void {
        if (! Dce::$config->log['access']['connect'] && ! Dce::$config->log['access']['logfile_power']) return;

        $clientInfo = $conn->server->getServer()->getClientInfo($conn->fd);
        $topic = sprintf("\n[#T;] (%s %s :%s) %s/%s/%s", $isConnect ? '连' : '断', $conn->server instanceof WebsocketServer ? 'websocket' : 'tcp',
            $clientInfo['server_port'] ?? '', $clientInfo['remote_ip'] ?? '', $conn->session->getId(), $conn->fd);
        self::push(LoggerType::Connect, $topic);
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
        self::push(LoggerType::Send, sprintf("\n[#T;] (发 %s %s) %s/%s", $type, $path ?: '', $ip, $sid), $data);
    }

    public static function rpcConnect($serverHost, $serverPort, $clientHost, bool $isConnect = true): void {
        if (! Dce::$config->log['rpc']['connect'] && ! Dce::$config->log['rpc']['logfile_power']) return;

        self::push(LoggerType::RpcConnect, sprintf('[#T;] (RPC%s %s) %s', $isConnect ? '连' : '断', "$serverHost:$serverPort", $clientHost));
    }

    public static function rpcRequest(string $method, array $arguments, string $clientIp): void {
        if (! Dce::$config->log['rpc']['request'] && ! Dce::$config->log['rpc']['logfile_power']) return;

        self::push(LoggerType::RpcRequest, sprintf('[#T;] (RPC求 %s) %s', $method, $clientIp), implode("\n", array_map(fn($a) => is_scalar($a) ? $a : json_encode($a, JSON_UNESCAPED_UNICODE), $arguments)));
    }

    public static function rpcResponse(string $method, mixed $result, string $clientIp): void {
        if (! Dce::$config->log['rpc']['response'] && ! Dce::$config->log['rpc']['logfile_power']) return;

        self::push(LoggerType::RpcResponse, sprintf('[#T;] (RPC应 %s) %s', $method, $clientIp), is_scalar($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param Cron $task
     * @param Cron[] $tasks
     */
    public static function cron(Cron $task, array $tasks): void {
        $topic = sprintf("\n[#T;] (开始) %s", $task->command);

        self::push(LoggerType::Cron, $topic);

        ($tasksFile = self::standardConfigLogfile(Dce::$config->log['cron'], 'tasks'))
            && self::write($tasksFile, array_reduce($tasks, fn($c, $t) => $c . ($c ? '\n' : '') . $t->format(), ''));
    }

    public static function cronDone(Cron $task, string $output): void {
        $topic = sprintf("%s\n[#T;] (完成) %s\n", $output, $task->command);

        self::push(LoggerType::CronDone, $topic);
    }

    public static function showCron(bool $showStatus): string {
        return substr(file_get_contents(Dce::$config->log['common']['root'] . self::standardConfigLogfile(Dce::$config->log['cron'], $showStatus ? 'tasks' : '')), - 16384);
    }

    public static function queryRequest(string $host, string $port, string $dbName, string $sql): string {
        if (Dce::$config->log['db']['console'] || Dce::$config->log['db']['logfile_power'])
            self::push(LoggerType::QueryRequest, sprintf("[#T;] (查 %s:%s/%s)", $host, $port, $dbName), $sql);
        return '';
    }

    public static function queryResponse(string $logId, mixed ... $args): void {
        //
    }

    public static function dce(Stringable|string $text): void {
        if (! isset(self::$isServerStart)) {
            global $argv;
            self::$isServerStart = in_array('start', $argv);
            self::$simpleLogger = SimpleLogger::inst();
            SwooleUtility::inSwoole() && self::$tableLogger = TableLogger::inst();
        }

        // 服务启动时才打印Dce日志
        self::$isServerStart && self::push(LoggerType::Dce, sprintf('[#T;] %s', $text));
    }

    private static function push(LoggerType $type, string $topic, string $content = null): void {
        $content !== null && ! mb_detect_encoding(mb_substr($content, -32), null, true) && $content = '(binary stream)';
        // SwooleUtility::inCoroutine() && self::$isServerStart ? self::$tableLogger->push($type, $topic, $content) : self::$simpleLogger->push($type, $topic, $content);
        self::$simpleLogger->push($type, $topic, $content);
    }

    public static function standardConfigLogfile(array $config, string $replacement = null): string {
        return $config['logfile_power'] ? sprintf($config['logfile'], $replacement ?: date($config['logfile_format'])) : '';
    }

    public static function console(Stringable|string $text, string $suffix = "\n", string $prefix = null): void {
        $prefix ??= sprintf('[%s] ', date('d H:i:s'));
        DCE_CLI_MODE && print("$prefix$text$suffix");
    }

    public static function write(string $filepath, string $content): void {
        static $fileOutput;
        $fileOutput ??= new FileOutput(Dce::$config->log['common']['root']);
        $fileOutput->push($filepath, $content);
    }
}
