<?php
/**
 * Author: Drunk
 * Date: 2020-04-21 19:16
 */

namespace websocket\service;

use dce\base\Exception;
use dce\Dce;
use dce\log\LogManager;
use dce\project\ProjectManager;
use dce\project\request\RequestManager;
use dce\project\session\Session;
use dce\project\session\SessionManager;
use dce\service\server\RawRequestConnection;
use dce\service\server\ServerMatrix;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

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
            throw new WebsocketException(WebsocketException::RAW_REQUEST_WEBSOCKET_CLASS_ERROR);
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

        $this->server->on('open', fn(Server $server, Request $request) => Exception::callCatch(fn() => $this->takeoverOpen($server, $request)));

        $this->server->on('message', fn(Server $server, Frame $frame) => Exception::callCatch(fn() => $this->takeoverMessage($server, $frame)));

        $this->server->on('close', fn(Server $server, int $fd, int $reactorId) => Exception::callCatch(fn() => $this->takeoverClose($server, $fd, $reactorId)));

        if ($websocketConfig['enable_http'] ?? false) {
            $this->server->on('request', fn(Request $request, Response $response) => Exception::callCatch(fn() => $this->takeoverRequest($request, $response)));
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
        echo self::$langStarted->format('Websocket', $host, $port);
        $this->server->start();
    }

    /**
     * 接管连接打开事件
     * @param Server $server
     * @param Request $request
     */
    protected function takeoverOpen(Server $server, Request $request): void {
        $session = Session::newBySid(Session::getSid($request) ?: true);
        LogManager::connect($this, $request->fd, $session->getId());
        Dce::$cache->var->set(['session', $request->fd], $session);
        Dce::$cache->var->set(['cookie', $request->fd], $request->cookie);
        SessionManager::inst()->connect($session->getId(), $request->fd, $this->apiHost, $this->apiPort, SessionManager::EXTRA_WS);
        $this->eventOnOpen($server, $request);
    }

    /**
     * 让DCE接管Websocket消息
     * @param Server $server
     * @param Frame $frame
     */
    private function takeoverMessage(Server $server, Frame $frame): void {
        Exception::callCatch([RequestManager::class, 'route'], static::$rawRequestWebsocketClass, $this, $frame);
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
        LogManager::send($this, $fd, $data, $path);
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
