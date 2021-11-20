<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/2/23 7:10
 */

namespace dce\rpc;

use dce\event\Daemon;
use dce\loader\Loader;
use dce\log\LogManager;
use Swoole\Coroutine\Server;
use Swoole\Server as AsyncServer;
use Throwable;

class RpcServer {
    /** @var RpcHost[] */
    private array $rpcHosts;

    /** @var RpcHost[] */
    private array $rpcHostsFdMapping;

    /** @var array 预载命名空间目录 */
    private array $prepares = [];

    /** @var array 预载文件 */
    private array $preloadFiles = [];

    /** @var Server[]|AsyncServer[] 服务器列表 */
    private array $servers = [];

    private function __construct() {}

    /**
     * 实例化方法, 代替构造函数, 方便连写
     * @param RpcHost|null $rpcHost
     * @return RpcServer
     */
    public static function new(RpcHost|null $rpcHost = null): self {
        $inst = new self;
        return $rpcHost ? $inst->addHost($rpcHost) : $inst;
    }

    /**
     * 实例化一个主机/端口对象
     * @param string $host
     * @param int $port 不支持传0作为随机端口, 当传入0时, 会将其视为unix sock, 会自动给host补上"unix:"前缀
     * @return RpcHost
     */
    public static function host(string $host, int $port = RpcUtility::DEFAULT_TCP_PORT): RpcHost {
        return new RpcHost([
            'host' => RpcUtility::uniqueSock($host),
            'port' => $port,
        ]);
    }

    /**
     * 增加一个服务主机/端口
     * @param RpcHost $rpcHost
     * @return $this
     */
    public function addHost(RpcHost $rpcHost): self {
        $this->rpcHosts[] = $rpcHost;
        return $this;
    }

    /**
     * 预加载Rpc命名空间
     * @param string $wildcard
     * @param string $root
     * @return $this
     * @throws null
     */
    public function prepare(string $wildcard, string $root): self {
        if (! is_dir($root)) {
            throw new RpcException(RpcException::PREPARE_ROOT_NOT_EXISTS);
        }
        $this->prepares[] = ['wildcard' => $wildcard, 'root' => $root];
        return $this;
    }

    /**
     * 预加载Rpc类文件
     * @param string $filename
     * @return $this
     * @throws null
     */
    public function preload(string $filename): self {
        if (! is_file($filename)) {
            throw new RpcException(RpcException::PRELOAD_NOT_EXISTS);
        }
        $this->preloadFiles[] = $filename;
        return $this;
    }

    /**
     * 启动Rpc服务
     * @param bool $useAsyncServer 是否使用异步Tcp服务（否则使用协程版）
     * @return $this
     */
    public function start(bool $useAsyncServer = false): self {
        $this->prepareService();
        $this->run($useAsyncServer);
        return $this;
    }

    /**
     * 预加载Rpc命名空间或者类文件
     */
    private function prepareService(): void {
        foreach ($this->preloadFiles as $file) {
            Loader::once($file);
        }
        foreach ($this->prepares as ['wildcard' => $wildcard, 'root' => $root]) {
            // 若配置了rpc_servers且RcpClient与RpcServer在同一个生命周期执行, 可能会导致RpcServer环境对一个相同的命名空间即prepare了RPC Callback又prepare了root path,
            // 当RpcServer执行被调用的方法时会遍历这两个定义, 先执行callback, 再require类文件, 因为callback时会创建一个类, 该类会发起对RpcServer的远程call,
            // 这会导致了一个无限RpcCall嵌套, 使程序陷入死循环, 所以我们在这将root路径prepare到最前的定义, 使被调用时先从root路径下加载类文件解决前面那个问题
            // 但是, 如果root路径下类文件不存在或未定义该类, 还是会进入这个死循环, 所以使用远程服务时务必定义好远程类方法
            Loader::prepare($wildcard, $root, true);
        }
    }

    /**
     * 启动所有服务
     * @param bool $useAsyncServer
     */
    private function run(bool $useAsyncServer = false): void {
        LogManager::dce(lang(['RPC 服务器已启动.', 'RPC server is started']));
        if ($useAsyncServer) {
            $this->runAsyncServer();
        } else {
            $this->runCoroutineServer();
        }
    }

    /**
     * 异步版RpcServer
     */
    private function runAsyncServer(): void {
        $rpcHosts = array_slice($this->rpcHosts, 0);
        $rpcHost = array_shift($rpcHosts);
        $this->servers[] = $server = new AsyncServer($rpcHost->host, $rpcHost->port, SWOOLE_PROCESS, $rpcHost->isUnixSock ? SWOOLE_SOCK_UNIX_STREAM : SWOOLE_SOCK_TCP);
        foreach ($rpcHosts as $rpcHost) {
            $server->listen($rpcHost->host, $rpcHost->port, $rpcHost->isUnixSock ? SWOOLE_SOCK_UNIX_STREAM : SWOOLE_SOCK_TCP);
        }
        $server->on('WorkerStart', fn() => srand());
        $server->on('connect', function(AsyncServer $server, int $fd, int $reactorId) {
            $clientInfo = $server->getClientInfo($fd, $reactorId);
            LogManager::rpcConnect($server->ports[0]->host, $clientInfo['server_port'], $clientInfo['remote_ip']);
        });
        $server->on('close', function(AsyncServer $server, int $fd, int $reactorId) {
            $clientInfo = $server->getClientInfo($fd, $reactorId);
            LogManager::rpcConnect($server->ports[0]->host, $clientInfo['server_port'], $clientInfo['remote_ip'], false);
        });
        $server->on('receive', function (AsyncServer $aServer, int $fd, int $reactorId, string $requestData) use ($rpcHost) {
            $responseData = self::catchCall(function() use($aServer, $fd, $reactorId, $requestData) {
                if (! $requestData) return null; // "0"视为ping, 无需处理
                $clientInfo = $aServer->getClientInfo($fd, $reactorId);
                return [fn($data) => $aServer->send($fd, $data), ['client_ip' => $clientInfo['remote_ip']], $requestData,
                    $this->matchRpcHostByServerFd($aServer, $clientInfo['server_fd'])];
            });
            is_string($responseData) && $aServer->send($fd, $requestData);
        });
        $server->addProcess(Daemon::runDaemon(null));
        $server->start();
    }

    /**
     * 根据ServerFd匹配RpcHost
     * @param AsyncServer $server
     * @param int $serverFd
     * @return RpcHost
     */
    private function matchRpcHostByServerFd(AsyncServer $server, int $serverFd): RpcHost {
        if (! isset($this->rpcHostsFdMapping)) {
            foreach ($server->ports as $port) {
                foreach ($this->rpcHosts as $rpcHost) {
                    if ($port->host === $rpcHost->host && $port->port === $rpcHost->port && $port->type === $rpcHost->isUnixSock ? SWOOLE_SOCK_UNIX_STREAM : SWOOLE_SOCK_TCP) {
                        $this->rpcHostsFdMapping[$port->sock] = $rpcHost;
                    }
                }
            }
        }
        return $this->rpcHostsFdMapping[$serverFd];
    }

    /**
     * 协程版RpcServer
     */
    private function runCoroutineServer(): void {
        foreach ($this->rpcHosts as $rpcHost) {
            go(function () use($rpcHost) {
                $this->servers[] = $server = new Server(($rpcHost->isUnixSock ? 'unix:' : '') . $rpcHost->host, $rpcHost->port);
                $server->handle(function (Server\Connection $connection) use ($rpcHost) {
                    $socket = $connection->exportSocket();
                    $clientInfo = $socket->getpeername();
                    $serverInfo = $socket->getsockname();
                    LogManager::rpcConnect($serverInfo['address'], $serverInfo['port'], $clientInfo['address']);
                    // 连接错误时会返回false，退出循环停止监听
                    while (false !== $responseData = self::catchCall(function() use($connection, $rpcHost, $socket, $clientInfo) {
                        $requestData = $connection->recv();
                        if (! $requestData) {
                            $socket->checkLiveness(); // 若客户端异常断开，将导致服务端状态无法更新，此方法可更新服务端的状态
                            if ($socket->errCode > 0) {
                                $connection->close();
                                throw (new RpcException(RpcException::INVALID_CONNECTION))->format($socket->errCode, $socket->errMsg);
                            }
                            return null; // 如果是"0", 则为ping
                        }
                        return [fn($data) => $connection->send($data), ['client_ip' => $clientInfo['address']], $requestData, $rpcHost];
                    })) {
                        is_string($responseData) && $connection->send($responseData); // 这里只会send异常
                    }
                    LogManager::rpcConnect($serverInfo['address'], $serverInfo['port'], $clientInfo['address'], false);
                });
                $server->start();
            });
        }
    }

    /**
     * @param callable():array{callable<string>, array, string, RpcHost}|false|null $executor
     * @return string|false|null
     * @throws RpcException
     */
    private static function catchCall(callable $executor): string|false|null {
        try {
            if (! $info = call_user_func($executor)) return $info;
            [$sender, $clientInfo, $requestData, $rpcHost] = $info;

            [$className, $methodName, $arguments] = self::accept($clientInfo, $requestData, $rpcHost);
            LogManager::rpcRequest("$className::$methodName", $arguments, $clientInfo['client_ip']);
            // 执行被请求的Rpc方法
            $result = self::execute($className, $methodName, $arguments);
            LogManager::rpcResponse("$className::$methodName", $result, $clientInfo['client_ip']);
            // 打包Rpc方法的执行结果并将其响应给客户端（在这里发送正常内容而不统一在外面，是为了能一起捕获send可能发生的异常）
            call_user_func($sender, self::package($result));
            return null;
        } catch (Throwable $throwable) {
            LogManager::console(RpcException::render($throwable), prefix: '');
            return $throwable instanceof RpcException && $throwable->getCode() === RpcException::INVALID_CONNECTION ? false : self::package($throwable);
        }
    }

    /**
     * 接受并处理RPC请求参数
     * @param array $clientInfo
     * @param string $requestData
     * @param RpcHost $rpcHost
     * @return array
     * @throws RpcException
     */
    private static function accept(array $clientInfo, string $requestData, RpcHost $rpcHost): array {
        [$authToken] = RpcUtility::decode(RpcUtility::REQUEST_FORMATTER, $requestData, 1, $streamOffset);
        // 先解token, 校验通过才继续解后面的数据
        self::authentication($rpcHost, $clientInfo['client_ip'], $authToken);
        [$className, $methodName, $arguments] = RpcUtility::decode(RpcUtility::REQUEST_FORMATTER, $requestData, 1, $streamOffset);
        return [$className, $methodName, unserialize($arguments)];
    }

    /**
     *         本机 本地 白名单 密匙
     * 限本机    1   0     0    0
     * 限本地    1   1     0    0
     * IP白名单  1   0     1    0
     * 限密匙    1   0     0    1
     * @param RpcHost $rpcHost
     * @param string $clientIp
     * @param string $authToken
     * @throws RpcException
     */
    private static function authentication(RpcHost $rpcHost, string $clientIp, string $authToken = ''): void {
        // 凡是本机请求, 无视授权限制
        if ($rpcHost->isUnixSock || '127.0.0.1' === $clientIp) return;

        $rpcHost->needNative && throw new RpcException(RpcException::NEED_NATIVE);
        $rpcHost->needLocal && ! RpcUtility::isLocalIp($clientIp) && throw new RpcException(RpcException::NEED_LOCAL);
        $rpcHost->ipWhiteList && ! in_array($clientIp, $rpcHost->ipWhiteList) && throw new RpcException(RpcException::NOT_IN_WHITE_LIST);
        $rpcHost->password && ! RpcUtility::verifyToken($rpcHost->password, $authToken) && throw new RpcException(RpcException::VALID_FAILED);
    }

    /**
     * 执行RPC服务并返回结果
     * @param string $className
     * @param string $methodName
     * @param array $arguments
     * @return mixed
     * @throws RpcException
     */
    private static function execute(string $className, string $methodName, array $arguments): mixed {
        ! is_subclass_of($className, RpcMatrix::class) && throw (new RpcException(RpcException::NOT_RPC_CLASS))->format($className);
        return call_user_func_array([$className, $methodName], $arguments);
    }

    /**
     * 打包服务结果
     * @param mixed $result
     * @return string
     * @throws RpcException
     */
    private static function package(mixed $result): string {
        if (is_object($result)) {
            $response = [RpcUtility::RESULT_TYPE_OBJECT, serialize($result)];
        } else {
            $response = [RpcUtility::RESULT_TYPE_GENERAL, json_encode($result, JSON_UNESCAPED_UNICODE)];
        }
        $response = RpcUtility::encode(RpcUtility::RESPONSE_FORMATTER, ... $response);
        return $response;
    }
}
