<?php
/**
 * Author: Drunk
 * Date: 2020-04-28 19:38
 */

namespace tcp\service;

use dce\base\Exception;
use dce\project\ProjectManager;
use dce\service\server\RawRequestConnection;
use dce\service\server\ServerMatrix;
use Swoole\Server;

class TcpServer extends ServerMatrix {
    protected static string $localRpcHost = '/var/run/dce_tcp_api.sock';

    private Server $server;

    /**
     * 启动Tcp服务器
     * @param array $param
     * @throws TcpException
     */
    final public function start(array $param): void {
        if (! is_subclass_of(static::$rawRequestTcpClass, RawRequestConnection::class)) {
            throw new TcpException(TcpException::RAW_REQUEST_TCP_CLASS_ERROR);
        }

        $this->projectConfig = ProjectManager::get('tcp')->getConfig();
        $tcpConfig = $this->projectConfig->tcp;
        $host = $param['host'] ?? $tcpConfig['host'];
        $port = $param['port'] ?? $tcpConfig['port'];
        $mode = $param['mode'] ?? $tcpConfig['mode'];
        $sockType = $param['sock_type'] ?? $tcpConfig['sock_type'];
        $extraPorts = $tcpConfig['extra_ports'] ?? [];
        $this->apiHost = $param['api_host'] ?? $tcpConfig['api_host'] ?? '';
        $this->apiPort = $param['api_port'] ?? $tcpConfig['api_port'] ?? 0;
        $this->apiPassword = $param['api_password'] ?? $tcpConfig['api_password'] ?? '';

        $swooleTcpConfig = $this->projectConfig->swooleTcp ?: [];
        $swooleTcpEvents = $this->eventsToBeBound();

        $sslBit = ($swooleTcpConfig['ssl_key_file'] ?? 0) ? SWOOLE_SSL : 0;
        $this->server = new Server($host, $port, $mode, $sockType | $sslBit);
        // 拓展自定义Swoole Server配置
        $this->server->set($swooleTcpConfig);
        foreach ($extraPorts as ['host' => $extraHost, 'port' => $extraPort, 'sock_type' => $extraSockType]) {
            // 同时监听额外的端口
            $this->server->listen($extraHost, $extraPort, $extraSockType);
        }
        $this->eventBeforeStart($this->server);

        $this->server->on('connect', fn(Server $server, int $fd, int $reactorId) => Exception::catchRequest(fn() => $this->takeoverConnect($server, $fd, $reactorId)));

        $this->server->on('receive', fn(Server $server, int $fd, int $reactorId, string $data) => Exception::catchRequest(fn() => $this->takeoverReceive($server, $fd, $reactorId, $data)));

        $this->server->on('packet', fn(Server $server, string $data, array $clientInfo) => Exception::catchRequest(fn() => $this->takeoverPacket($server, $data, $clientInfo)));

        $this->server->on('close', fn(Server $server, int $fd, int $reactorId) => Exception::catchRequest(fn() => $this->takeoverClose($server, $fd, $reactorId)));

        // 扩展自定义的Swoole Server事件回调
        foreach ($swooleTcpEvents as $eventName => $eventCallback) {
            $this->server->on($eventName, $eventCallback);
        }

        $this->runApiService();
        echo self::$langStarted->format('Tcp/Udp', $host, $port);
        $this->server->start();
    }

    /**
     * 取Tcp Server服务
     * @return Server
     */
    final public function getServer(): Server {
        return $this->server;
    }
}
