<?php
/**
 * Author: Drunk
 * Date: 2020-04-21 19:16
 */

namespace websocket\service;

use dce\base\Exception;
use dce\log\LogManager;
use dce\project\node\NodeManager;
use dce\project\ProjectManager;
use dce\project\request\RequestManager;
use dce\project\session\Session;
use dce\project\session\SessionManager;
use dce\service\server\Connection;
use dce\service\server\RawRequestConnection;
use dce\service\server\ServerMatrix;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Swoole\Server as BaseServer;

class WebsocketServer extends ServerMatrix {
    protected static string $localRpcHost = '/var/run/dce_websocket_api.sock';

    /** @var class-string<RawRequestWebsocket> 定义RawRequest类名, (可在子类覆盖此属性使用自定义RawRequest类) */
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
        if (! is_subclass_of(static::$rawRequestWebsocketClass, RawRequestConnection::class))
            throw new WebsocketException(WebsocketException::RAW_REQUEST_WEBSOCKET_CLASS_ERROR);

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
        // 同时监听额外的端口
        foreach ($extraPorts as ['host' => $extraHost, 'port' => $extraPort])
            $this->server->listen($extraHost, $extraPort, SWOOLE_SOCK_TCP);
        $this->eventBeforeStart($this->server);

        $this->server->on('open', fn(Server $server, Request $request) => Exception::catchRequest(fn() => $this->takeoverOpen($server, $request)));

        $this->server->on('message', fn(Server $server, Frame $frame) => Exception::catchRequest(fn() => $this->takeoverMessage($server, $frame)));

        $this->server->on('close', fn(Server $server, int $fd, int $reactorId) => Exception::catchRequest(fn() => $server->getClientInfo($fd, $reactorId)['websocket_status'] && $this->takeoverClose($server, $fd, $reactorId)));

        if ($websocketConfig['enable_http'] ?? false)
            $this->server->on('request', fn(Request $request, Response $response) => Exception::catchRequest(fn() => $this->takeoverRequest($request, $response)));

        // 扩展自定义的Swoole Server事件回调
        foreach ($swooleWebsocketEvents as $eventName => $eventCallback)
            $this->server->on($eventName, $eventCallback);

        // 开启Tcp/Udp支持
        if (is_array($websocketConfig['enable_tcp_ports'] ?? false))
            $this->enableTcpPorts($websocketConfig['enable_tcp_ports'], $this->projectConfig->swooleTcp ?: []);

        $this->runApiService();
        $this->printStartLog('Websocket', $host, $port, $extraPorts);
        $this->server->start();
    }

    /**
     * 接管连接打开事件
     * @param Server $server
     * @param Request $request
     */
    protected function takeoverOpen(Server $server, Request $request): void {
        Exception::catchRequest(function() use($server, $request) {
            $session = Session::newBySid(Session::getSid($request) ?: true);
            $initialNode = NodeManager::exists(static::$rawRequestWebsocketClass::CONNECTION_PATH);
            $conn = Connection::from($request->fd, $this)->setProps($initialNode, $session, $request);
            SessionManager::inst()->connect($conn, SessionManager::EXTRA_WS);
            if ($initialNode) {
                RequestManager::route(static::$rawRequestWebsocketClass, $this, $conn);
            } else {
                LogManager::connect($conn);
                $this->eventOnOpen($server, $request);
            }
        });
    }

    /**
     * 让DCE接管Websocket消息
     * @param Server $server
     * @param Frame $frame
     */
    private function takeoverMessage(Server $server, Frame $frame): void {
        Exception::catchRequest([RequestManager::class, 'route'], static::$rawRequestWebsocketClass, $this, $frame);
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
    final public function getServer(): BaseServer {
        return $this->server;
    }
}
