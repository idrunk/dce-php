<?php
/**
 * Author: Drunk
 * Date: 2020-04-28 19:38
 */

namespace tcp\service;

use dce\project\ProjectManager;
use dce\service\server\RawRequestConnection;
use dce\service\server\ServerMatrix;
use Swoole\Server;
use Throwable;

class TcpServer extends ServerMatrix {
    protected static string $localRpcHost = '/var/run/dce_tcp_api.sock';

    protected static string $serverApiPath = __DIR__ . '/TcpServerApi.php';

    protected static string $serverApiClass = 'rpc\tcp\service\TcpServerApi';

    private Server $server;

    /**
     * 启动Tcp服务器
     * @param array $param
     * @throws TcpException
     */
    final public function start(array $param): void {
        if (! is_subclass_of(static::$rawRequestTcpClass, RawRequestConnection::class)) {
            throw new TcpException('$rawRequestClass属性值非RawRequestPersistent类名');
        }

        $this->projectConfig = ProjectManager::get('tcp')->getConfig();
        $tcpConfig = $this->projectConfig->tcp;
        $host = $param['host'] ?? $tcpConfig['host'];
        $port = $param['port'] ?? $tcpConfig['port'];
        $mode = $param['mode'] ?? $tcpConfig['mode'];
        $sockType = $param['sock_type'] ?? $tcpConfig['sock_type'];
        $extraPorts = $tcpConfig['extra_ports'] ?? [];
        $this->apiHost = $tcpConfig['api_host'] ?? '';
        $this->apiPort = $tcpConfig['api_port'] ?? 0;
        $this->apiPassword = $tcpConfig['api_password'] ?? '';

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
        $this->sessionManager = $this->genSessionManager();

        $this->server->on('connect', function (Server $server, int $fd, int $reactorId) {
            try {
                $this->takeoverConnect($server, $fd, $reactorId);
            } catch (Throwable $throwable) {
                $this->handleException($throwable);
            }
        });

        $this->server->on('receive', function (Server $server, int $fd, int $reactorId, string $data) {
            try {
                $this->takeoverReceive($server, $fd, $reactorId, $data);
            } catch (Throwable $throwable) {
                $this->handleException($throwable);
            }
        });

        $this->server->on('packet', function(Server $server, string $data, array $clientInfo) {
            try {
                $this->takeoverPacket($server, $data, $clientInfo);
            } catch (Throwable $throwable) {
                $this->handleException($throwable);
            }
        });

        $this->server->on('close', function (Server $server, int $fd, int $reactorId) {
            try {
                $this->takeoverClose($server, $fd, $reactorId);
            } catch (Throwable $throwable) {
                $this->handleException($throwable);
            }
        });

        // 扩展自定义的Swoole Server事件回调
        foreach ($swooleTcpEvents as $eventName => $eventCallback) {
            $this->server->on($eventName, $eventCallback);
        }

        $this->runApiService();
        print_r("Tcp server started with {$host}:{$port}.\n");
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
