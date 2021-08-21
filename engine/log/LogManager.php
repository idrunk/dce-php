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
use dce\project\session\SessionManager;
use dce\service\server\ServerMatrix;
use Throwable;
use websocket\service\WebsocketServer;

final class LogManager {
    public static function init() {
        if (Dce::$config->log['db']['console']) {
            ScriptLogger::addDriver(new ScriptLoggerConsole());
        }
    }

    /**
     * 打印记录异常
     * @param Throwable $throwable
     * @param bool $isSimple
     */
    public static function exception(Throwable $throwable, bool $isSimple): void {
        $pureContent = self::exceptionRender($throwable, $isSimple);

        // 打印异常
        DCE_CLI_MODE && Dce::$config->log['exception']['console'] && print($pureContent);

        // 对closed异常记录日志
        if (! $isSimple && Dce::$config->log['exception']['log_file']) {
            $filename = sprintf(Dce::$config->log['exception']['log_file'], date(Dce::$config->log['exception']['log_name_format']));
            ! file_exists(dirname($filename)) && mkdir(dirname($filename), 0755, true);
            file_put_contents($filename, $pureContent, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * 渲染异常
     * @param Throwable $throwable
     * @param bool|null $simple {null: 渲染为通用响应结构体, true: 渲染为简介提示, false: 渲染为详细异常}
     * @param bool $html 是否用html包裹
     * @return string
     */
    public static function exceptionRender(Throwable $throwable, bool|null $simple = false, bool $html = false): string {
        $now = date('Y-m-d H:i:s');
        if ($simple === null) {
            $data = ['status' => false];
            $throwable->getCode() && $data['code'] = $throwable->getCode();
            $throwable->getMessage() && $data['message'] = $throwable->getMessage();
            $content = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            $content = $simple ? sprintf("[%s] (%s: %s) %s\n\n\n", $now, get_class($throwable), $throwable->getCode(), $throwable->getMessage())
                : sprintf("[%s] (%s: %s) %s\n\n%s\n\n\n", $now, get_class($throwable),$throwable->getCode(), $throwable->getMessage(), $throwable);
            $html && $content = sprintf('<!doctype html><html lang="zh"><head><meta charset="UTF-8"><title>%s</title></head><body><pre>%s</pre></body></html>', $throwable->getMessage(), $content);
        }
        return $content;
    }

    /**
     * 打印请求日志
     * @param Request $request
     */
    public static function request(Request $request): void {
        if (DCE_CLI_MODE && $request->config->log['access']['request'] && ($rawRequest = $request->rawRequest) && ! $rawRequest instanceof RawRequestCli) {
            $requestData = is_string($rawRequest->getRawData()) ? $rawRequest->getRawData() : json_encode($rawRequest->getRawData(), JSON_UNESCAPED_UNICODE);
            printf("[%s] (求 %s) %s%s\n\n\n", date('Y-m-d H:i:s'), $rawRequest->getClientInfo()['request'], $request->session->getId() ?? '', self::contentFormat($requestData));
        }
    }

    /**
     * 打印响应日志
     * @param RawRequest $rawRequest
     * @param mixed $data
     */
    public static function response(RawRequest $rawRequest, mixed $data): void {
        if (DCE_CLI_MODE && ($project = RequestManager::current()->project ?? null) && $project->getConfig()->log['access']['response']) {
            $request = RequestManager::current();
            $responseData = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
            printf("[%s] (应 %s) %s%s\n\n\n", date('Y-m-d H:i:s'), $rawRequest->getClientInfo()['request'], $request->session->getId() ?? '', self::contentFormat($responseData, false));
        }
    }

    /**
     * 打印连接日志
     * @param ServerMatrix $server
     * @param int $fd
     * @param string $sid
     */
    public static function connect(ServerMatrix $server, int $fd, string $sid): void {
        if (DCE_CLI_MODE && Dce::$config->log['access']['connect']) {
            $type = $server instanceof WebsocketServer ? 'websocket' : 'tcp';
            $ip = $server->getServer()->getClientInfo($fd)['remote_ip'] ?? '';
            printf("[%s] (连 %s %s) %s\n\n\n", date('Y-m-d H:i:s'), $type, $ip, $sid);
        }
    }

    /**
     * 打印连接断开日志
     * @param ServerMatrix $server
     * @param int $fd
     */
    public static function disconnect(ServerMatrix $server, int $fd): void {
        if (DCE_CLI_MODE && Dce::$config->log['access']['connect']) {
            $type = $server instanceof WebsocketServer ? 'websocket' : 'tcp';
            $ip = $server->getServer()->getClientInfo($fd)['remote_ip'] ?? '';
            $sid = SessionManager::inst()->getFdForm($fd, $server->apiHost, $server->apiPort)['sid'] ?? '';
            printf("[%s] (断 %s %s) %s\n\n\n", date('Y-m-d H:i:s'), $type, $ip, $sid);
        }
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
        if (DCE_CLI_MODE && Dce::$config->log['access']['send'] && (RequestManager::current()->fd ?? 0) !== $fd) {
            $isUdp = ! is_int($fd);
            $type = $isUdp ? 'udp' : ($server instanceof WebsocketServer ? 'websocket' : 'tcp');
            if (! $isUdp) {
                $ip = $server->getServer()->getClientInfo($fd)['remote_ip'] ?? '';
                $sid = SessionManager::inst()->getFdForm($fd, $server->apiHost, $server->apiPort)['sid'] ?? '';
            } else {
                $ip = $fd;
                $sid = ''; // udp未自动开启session，可能没有sid
            }
            printf("[%s] (发 %s %s/%s) %s%s\n\n\n", date('Y-m-d H:i:s'), $type, $ip, $path ?: '', $sid, self::contentFormat($data, false));
        }
    }

    private static function contentFormat(string $content, bool $short = true): string {
        $len = mb_strlen($content);
        $max = $short ? 1024 : 16384;
        $content && $content = "\n\n$content";
        return mb_substr($content, 0, $max) . ($len > $max ? '...' : '');
    }
}