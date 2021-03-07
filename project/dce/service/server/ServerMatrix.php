<?php
/**
 * Author: Drunk
 * Date: 2020-04-29 15:52
 */

namespace dce\service\server;

use dce\config\DceConfig;
use dce\Dce;
use dce\project\request\Request as DceRequest;
use dce\project\request\Session;
use dce\project\request\SessionManager;
use dce\rpc\RpcClient;
use dce\rpc\RpcServer;
use http\service\RawRequestHttpSwoole;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\Server;
use tcp\service\RawRequestTcp;
use tcp\service\RawRequestUdp;
use Throwable;

abstract class ServerMatrix {
    /** @var string SessionManager FdForm Tcp fd标记 */
    public const SM_EXTRA_TCP = 'tcp';

    /** @var string SessionManager FdForm Websocket fd标记 */
    public const SM_EXTRA_WS = 'ws';

    /** @var string 定义RawRequestHttp类名, (可在子类覆盖此属性使用自定义RawRequest类) */
    protected static string $rawRequestHttpClass = RawRequestHttpSwoole::class;

    /** @var string 定义RawRequestTcp类名 */
    protected static string $rawRequestTcpClass = RawRequestTcp::class;

    /** @var string 定义RawRequestUdp类名 */
    protected static string $rawRequestUdpClass = RawRequestUdp::class;

    /** @var string 服务接口Rpc监听主机地址 */
    protected static string $localRpcHost;

    /** @var string 服务接口文件路径 */
    protected static string $serverApiPath = __DIR__ . '/RpcServerApi.php';

    /** @var string 服务接口类名 */
    protected static string $serverApiClass = '\rpc\dce\service\RpcServerApi';

    /** @var DceConfig 当前Server项目配置 */
    protected DceConfig $projectConfig;

    /** @var string 对外Api服务地址 */
    public string $apiHost;

    /** @var int 对外Api服务端口 */
    public int $apiPort;

    /** @var string 对外Api服务的口令 */
    public string $apiPassword;

    public function __construct() {
        self::prepareApiRpc();
    }

    /**
     * 服务启动前回调, (可在子类覆盖自定义)
     * @param Server $server
     */
    protected function eventBeforeStart(Server $server): void {}

    /**
     * 开启连接回调, (可在子类覆盖自定义)
     * @param Server $server
     * @param int $fd
     * @param int $reactorId
     */
    protected function eventOnConnect(Server $server, int $fd, int $reactorId): void {}

    /**
     * 关闭连接回调, (可在子类覆盖自定义)
     * @param Server $server
     * @param int $fd
     * @param int $reactorId
     */
    protected function eventOnClose(Server $server, int $fd, int $reactorId): void {}

    /**
     * 取用户自定义的需要监听的其他事件, (可在子类覆盖自定义)
     * @return array
     */
    protected function eventsToBeBound(): array {
        return [];
    }

    /**
     * 启动Tcp/Udp支持, (在Http/Websocket服务同时开启Tcp/Udp)
     * @param array $tcpPorts
     * @param array $swooleTcpConfig
     */
    protected function enableTcpPorts(array $tcpPorts, array $swooleTcpConfig): void {
        foreach ($tcpPorts as ['host' => $host, 'port' => $port, 'sock_type' => $sockType]) {
            $portInstance = $this->getServer()->listen($host, $port, $sockType);
            $portInstance->set($swooleTcpConfig);
            if (in_array($sockType, [SWOOLE_SOCK_TCP, SWOOLE_SOCK_TCP6, SWOOLE_UNIX_STREAM])) {
                $portInstance->on('connect', function (Server $server, int $fd, int $reactorId) {
                    try {
                        $this->takeoverConnect($server, $fd, $reactorId);
                    } catch (Throwable $throwable) {
                        $this->handleException($throwable);
                    }
                });
                $portInstance->on('receive', function (Server $server, int $fd, int $reactorId, string $data) {
                    try {
                        $this->takeoverReceive($server, $fd, $reactorId, $data);
                    } catch (Throwable $throwable) {
                        $this->handleException($throwable);
                    }
                });
                $portInstance->on('close', function (Server $server, int $fd, int $reactorId) {
                    try {
                        $this->takeoverClose($server, $fd, $reactorId);
                    } catch (Throwable $throwable) {
                        $this->handleException($throwable);
                    }
                });
            } else {
                $portInstance->on('packet', function (Server $server, string $data, array $clientInfo) {
                    try {
                        $this->takeoverPacket($server, $data, $clientInfo);
                    } catch (Throwable $throwable) {
                        $this->handleException($throwable);
                    }
                });
            }
        }
    }

    /**
     * 接管连接事件
     * @param Server $server
     * @param int $fd
     * @param int $reactorId
     */
    protected function takeoverConnect(Server $server, int $fd, int $reactorId): void {
        $session = Session::newBySid(true); // 生成一个Session对象并var缓存
        Dce::$cache->var->set(['session', $fd], $session);
        SessionManager::inst()->connect($session->getId(), $fd, $this->apiHost, $this->apiPort, self::SM_EXTRA_TCP);
        $this->eventOnConnect($server, $fd, $reactorId);
    }

    /**
     * 让DCE接管Http请求
     * @param Request $request
     * @param Response $response
     * @throws Throwable
     */
    protected function takeoverRequest(Request $request, Response $response): void {
        $rawRequest = new static::$rawRequestHttpClass($this, $request, $response);
        $rawRequest->init();
        $dceRequest = new DceRequest($rawRequest);
        $dceRequest->route();
    }

    /**
     * 让DCE接管Tcp消息
     * @param Server $server
     * @param int $fd
     * @param int $reactorId
     * @param string $data
     * @throws \dce\project\request\RequestException
     */
    protected function takeoverReceive(Server $server, int $fd, int $reactorId, string $data): void {
        $rawRequest = new static::$rawRequestTcpClass($this, $data, $fd, $reactorId);
        $rawRequest->init();
        $request = new DceRequest($rawRequest);
        $request->route();
    }

    /**
     * 让DCE接管Udp消息
     * @param Server $server
     * @param string $data
     * @param array $clientInfo
     * @throws \dce\project\request\RequestException
     */
    protected function takeoverPacket(Server $server, string $data, array $clientInfo): void {
        $rawRequest = new static::$rawRequestUdpClass($this, $data, $clientInfo);
        $rawRequest->init();
        $request = new DceRequest($rawRequest);
        $request->route();
    }

    /**
     * 接管连接关闭事件 (用于Tcp/Websocket)
     * @param Server $server
     * @param int $fd
     * @param int $reactorId
     */
    protected function takeoverClose(Server $server, int $fd, int $reactorId): void {
        $this->eventOnClose($server, $fd, $reactorId);
        SessionManager::inst()->disconnect($fd, $this->apiHost, $this->apiPort);
        Dce::$cache->var->del(['session', $fd]);
    }

    /**
     * 处理请求接口异常
     * @param Throwable $throwable
     */
    protected function handleException(Throwable $throwable): void {
        testPoint("code:" . $throwable->getCode(), "file:" . $throwable->getFile() .":". $throwable->getLine(), $throwable->getMessage());
    }

    /**
     * 向Tcp客户端推送数据
     * @param int $fd
     * @param mixed $value
     * @param string|false $path
     * @return bool
     */
    public function send(int $fd, mixed $value, string|false $path): bool {
        $data = call_user_func([static::$rawRequestTcpClass, 'pack'], $path, $value);
        return $this->getServer()->send($fd, $data);
    }

    /**
     * 向Udp客户端推送数据
     * @param string $host
     * @param int $port
     * @param mixed $value
     * @param string|false $path
     * @return bool
     */
    public function sendTo(string $host, int $port, mixed $value, string|false $path): bool {
        $data = call_user_func([static::$rawRequestUdpClass, 'pack'], $path, $value);
        return $this->getServer()->sendto($host, $port, $data);
    }

    /**
     * 执行服务器Api服务
     */
    protected function runApiService(): void {
        $this->getServer()->addProcess(new Process(function () {
            $rpcServer = RpcServer::new(RpcServer::host(static::$localRpcHost))->preload(static::$serverApiPath);
            if ($this->apiHost && $this->apiPort) {
                // 这里不做过多的安全限制, 服务器接口安全可以通过物理服务器防火墙管理
                $rpcServer->addHost(RpcServer::host($this->apiHost, $this->apiPort)->setAuth($this->apiPassword));
            }
            $rpcServer->start(false);
            static::$serverApiClass::logServer($this);
            SessionManager::inst()->clear($this->apiHost, $this->apiPort);
        }, false, SOCK_STREAM, true));
    }

    /** 预挂载服务接口本地Rpc客户端 */
    private static function prepareApiRpc(): void {
        // 预挂载服务接口本地Rpc客户端
        RpcClient::preload(static::$serverApiClass, ['host' => static::$localRpcHost, 'port' => 0]);
    }

    /** 停止服务 */
    public function stop(): void {
        $this->getServer()->shutdown();
    }

    /** 重载服务 */
    public function reload(): void {
        $this->getServer()->reload();
    }

    /**
     * 获取服务状态
     * @return array
     */
    public function status(): array {
        return [
            'server' => $this->getServer()->stats(),
        ];
    }

    /**
     * 启动服务
     * @param array $param
     */
    abstract public function start(array $param): void;

    /**
     * 取Server
     * @return Server
     */
    abstract public function getServer(): Server;
}
