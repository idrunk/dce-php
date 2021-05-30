<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/12 4:59
 */

namespace http\service;

use dce\base\Exception;
use dce\project\ProjectManager;
use dce\project\request\RawRequestHttp;
use dce\service\server\ServerMatrix;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class HttpServer extends ServerMatrix {
    protected static string $localRpcHost = '/var/run/dce_http_api.sock';

    private Server $server;

    /**
     * 启动Http服务器
     * @param array $param
     * @throws HttpException
     */
    final public function start(array $param): void {
        if (! is_subclass_of(static::$rawRequestHttpClass, RawRequestHttp::class)) {
            throw new HttpException(HttpException::RAW_REQUEST_HTTP_CLASS_ERROR);
        }

        $this->projectConfig = ProjectManager::get('http')->getConfig();
        $httpConfig = $this->projectConfig->http;
        $host = $param['host'] ?? $httpConfig['host'];
        $port = $param['port'] ?? $httpConfig['port'];
        $extraPorts = $httpConfig['extra_ports'] ?? [];
        $this->apiHost = $httpConfig['api_host'] ?? '';
        $this->apiPort = $httpConfig['api_port'] ?? 0;
        $this->apiPassword = $httpConfig['api_password'] ?? '';

        $swooleHttpConfig = $this->projectConfig->swooleHttp;
        $swooleHttpEvents = $this->eventsToBeBound();

        $sslBit = ($swooleHttpConfig['ssl_key_file'] ?? 0) ? SWOOLE_SSL : 0;
        $this->server = new Server($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | $sslBit);
        // 拓展自定义Swoole Server配置
        $this->server->set($swooleHttpConfig);
        foreach ($extraPorts as ['host' => $extraHost, 'port' => $extraPort]) {
            // 同时监听额外的端口
            $this->server->listen($extraHost, $extraPort, SWOOLE_SOCK_TCP);
        }
        $this->eventBeforeStart($this->server);

        $this->server->on('request', fn(Request $request, Response $response) => Exception::callCatch(fn() => $this->takeoverRequest($request, $response)));

        // 扩展自定义的Swoole Server事件回调
        foreach ($swooleHttpEvents as $eventName => $eventCallback) {
            $this->server->on($eventName, $eventCallback);
        }

        // 开启Tcp/Udp支持
        if (is_array($httpConfig['enable_tcp_ports'] ?? false)) {
            $this->enableTcpPorts($httpConfig['enable_tcp_ports'], $this->projectConfig->swooleTcp ?: []);
        }

        $this->runApiService();
        echo self::$langStarted->format('Http', $host, $port);
        $this->server->start();
    }

    /**
     * 取Http Server服务
     * @return Server
     */
    final public function getServer(): Server {
        return $this->server;
    }
}
