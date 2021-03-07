<?php
/**
 * Author: Drunk
 * Date: 2020-04-21 19:16
 */

namespace websocket\service;

use dce\Dce;
use dce\project\ProjectManager;
use dce\project\request\Request as DceRequest;
use dce\project\request\Session;
use dce\project\request\SessionManager;
use dce\service\server\RawRequestConnection;
use dce\service\server\ServerMatrix;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Throwable;

class WebsocketServer extends ServerMatrix {
    protected static string $localRpcHost = '/var/run/dce_websocket_api.sock';

    /** @var string 定义RawRequest类名, (可在子类覆盖此属性使用自定义RawRequest类) */
    protected static string $rawRequestWebsocketClass = RawRequestWebsocket::class;

    private Server $server;

    /**
     * 开启连接回调, (可在子类覆盖自定义)
     * @param Server $server
     * @param Request $request
     */
    protected function eventOnOpen(Server $server, Request $request): void {}

    /**
     * 启动Websocket服务器
     * @param array $param
     * @throws WebsocketException
     */
    final public function start(array $param): void {
        if (! is_subclass_of(static::$rawRequestWebsocketClass, RawRequestConnection::class)) {
            throw new WebsocketException('$rawRequestClass属性值非RawRequestPersistent类名');
        }

        $this->projectConfig = ProjectManager::get('websocket')->getConfig();
        $websocketConfig = $this->projectConfig->websocket;
        $host = $param['host'] ?? $websocketConfig['host'];
        $port = $param['port'] ?? $websocketConfig['port'];
        $extraPorts = $websocketConfig['extra_ports'] ?? [];
        $this->apiHost = $param['api_host'] ?? $websocketConfig['api_host'] ?? '';
        $this->apiPort = $param['api_port'] ?? $websocketConfig['api_port'] ?? 0;
        $this->apiPassword = $param['api_password'] ?? $websocketConfig['api_password'] ?? '';

        $swooleWebsocketConfig = $this->projectConfig->swooleWebsocket ?: [];
        $swooleWebsocketEvents = $this->eventsToBeBound();

        $sslBit = ($swooleWebsocketConfig['ssl_key_file'] ?? 0) ? SWOOLE_SSL : 0;
        $this->server = new Server($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | $sslBit);
        // 拓展自定义Swoole Server配置
        $this->server->set($swooleWebsocketConfig);
        foreach ($extraPorts as ['host' => $extraHost, 'port' => $extraPort]) {
            // 同时监听额外的端口
            $this->server->listen($extraHost, $extraPort, SWOOLE_SOCK_TCP);
        }
        $this->eventBeforeStart($this->server);

        $this->server->on('open', function (Server $server, Request $request) {
            try {
                $this->takeoverOpen($server, $request);
            } catch (Throwable $throwable) {
                $this->handleException($throwable);
            }
        });

        $this->server->on('message', function (Server $server, Frame $frame) {
            try {
                $this->takeoverMessage($server, $frame);
            } catch (Throwable $throwable) {
                $this->handleException($throwable);
            }
        });

        $this->server->on('close', function (Server $server, int $fd, int $reactorId) {
            // 不仅websocket的close会进到这里, http以及一个疑似ws握手的连接close也会进来.
            try {
                $this->takeoverClose($server, $fd, $reactorId);
            } catch (Throwable $throwable) {
                $this->handleException($throwable);
            }
        });

        if ($websocketConfig['enable_http'] ?? false) {
            $this->server->on('request', function (Request $request, Response $response) {
                try {
                    $this->takeoverRequest($request, $response);
                } catch (Throwable $throwable) {
                    $this->handleException($throwable);
                }
            });
        }

        // 扩展自定义的Swoole Server事件回调
        foreach ($swooleWebsocketEvents as $eventName => $eventCallback) {
            $this->server->on($eventName, $eventCallback);
        }

        // 开启Tcp/Udp支持
        if (is_array($websocketConfig['enable_tcp_ports'] ?? false)) {
            $this->enableTcpPorts($websocketConfig['enable_tcp_ports'], $this->projectConfig->swooleTcp ?: []);
        }

        $this->runApiService();
        print_r("Websocket server started with {$host}:{$port}.\n");
        $this->server->start();
    }

    /**
     * 接管连接打开事件
     * @param Server $server
     * @param Request $request
     */
    protected function takeoverOpen(Server $server, Request $request): void {
        $session = Session::newBySid($request->cookie[Session::getSidName()] ?? true);
        Dce::$cache->var->set(['session', $request->fd], $session);
        SessionManager::inst()->connect($session->getId(), $request->fd, $this->apiHost, $this->apiPort, self::SM_EXTRA_WS);
        $this->eventOnOpen($server, $request);
    }

    /**
     * 让DCE接管Websocket消息
     * @param Server $server
     * @param Frame $frame
     * @throws \dce\project\request\RequestException
     */
    private function takeoverMessage(Server $server, Frame $frame): void {
        $rawRequest = new static::$rawRequestWebsocketClass($this, $frame);
        $rawRequest->init();
        $request = new DceRequest($rawRequest);
        $request->route();
    }

    /**
     * 向客户端推送数据
     * @param int $fd
     * @param mixed $value
     * @param string|false $path
     * @return bool
     */
    public function push(int $fd, mixed $value, string|false $path): bool {
        $data = call_user_func([static::$rawRequestWebsocketClass, 'pack'], $path, $value);
        return $this->server->push($fd, $data);
    }

    /**
     * 取Websocket Server服务
     * @return Server
     */
    final public function getServer(): Server {
        return $this->server;
    }
}
