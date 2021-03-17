<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/2/23 7:10
 */

namespace dce\rpc;

use dce\Dce;
use dce\Loader;
use Swoole\Process;
use Swoole\Coroutine\Server;
use Swoole\Server as aServer;
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

    /** @var Server[]|aServer[] 服务器列表 */
    private array $servers = [];

    /** @var Process 自动创建的进程 */
    private Process $process;

    private function __construct() {}

    /**
     * 实例化方法, 代替构造函数, 方便连写
     * @param RpcHost $rpcHost
     * @return RpcServer
     */
    public static function new(RpcHost $rpcHost) {
        return (new self)->addHost($rpcHost);
    }

    /**
     * 实例化一个主机/端口对象
     * @param string $host
     * @param int $port 不支持传0作为随机端口, 当传入0时, 会将其视为unix sock, 会自动给host补上"unix:"前缀
     * @return RpcHost
     */
    public static function host(string $host = RpcUtility::DEFAULT_TCP_HOST, int $port = RpcUtility::DEFAULT_TCP_PORT): RpcHost {
        return new RpcHost([
            'host' => $host,
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
            throw new RpcException('预载命名空间根目录不存在');
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
            throw new RpcException('预载文件不存在');
        }
        $this->preloadFiles[] = $filename;
        return $this;
    }

    /** 关闭服务 */
    public function stop(): void {
        if (isset($this->process)) {
            // mark 此处无法正常关闭
            Process::kill($this->process->pid);
        } else {
            foreach ($this->servers as $server) {
                $server->shutdown();
            }
        }
    }

    /**
     * 启动Rpc服务
     * @param callable|bool $callback 新进程启动回调, 为布尔值时表示是否创建新进程
     * @param bool $useAsyncServer 是否使用异步Tcp服务
     * @return $this
     */
    public function start(callable|bool $callback = true, bool $useAsyncServer = false): self {
        if ($callback) {
            $process = new Process(function () use ($callback, $useAsyncServer) {
                Process::signal(SIGTERM, fn() => $this->stop());
                $this->prepareService();
                if (is_callable($callback)) {
                    call_user_func($callback);
                }
                $this->run($useAsyncServer);
            }, false, SOCK_STREAM, $useAsyncServer ? false : true);
            $process->start();
            $this->process = $process;
            usleep(200000);
        } else {
            $this->prepareService();
            $this->run($useAsyncServer);
        }
        return $this;
    }

    /**
     * 预加载Rpc命名空间或者类文件
     */
    private function prepareService() {
        foreach ($this->preloadFiles as $file) {
            Loader::once($file);
        }
        foreach ($this->prepares as ['wildcard' => $wildcard, 'root' => $root]) {
            // 若配置了rpc_servers且RcpClient与RpcServer在同一个生命周期执行, 可能会导致RpcServer环境对一个相同的命名空间即prepare了RPC Callback又prepare了root path,
            // 当RpcServer执行被调用的方法时会遍历这两个定义, 先执行callback, 再require类文件, 因为callback时会创建一个类, 该类会发起对RpcServer的远程call,
            // 这会导致了一个无限RpcCall嵌套, 使程序陷入死循环, 所以我们在这将root路径prepare到最前的定义, 使被调用时先从root路径下加载类文件解决前面那个问题
            // 但是, 如果root路径下类文件不存在, 或者文件存在却没用定义该类, 还是会进入这个死循环, 所以使用远程服务时务必定义好远程类方法
            Loader::prepare($wildcard, $root, true);
        }
    }

    /**
     * 启动所有服务
     * @param bool $useAsyncServer
     */
    private function run(bool $useAsyncServer = false): void {
        if ($useAsyncServer) {
            $this->runAsyncServer();
        } else {
            $this->runCoroutineServer();
        }
    }

    /**
     * 异步版RpcServer
     */
    private function runAsyncServer() {
        $rpcHosts = array_slice($this->rpcHosts, 0);
        $rpcHost = array_shift($rpcHosts);
        $this->servers[] = $server = new aServer($rpcHost->host, $rpcHost->port, SWOOLE_PROCESS, $rpcHost->isUnixSock ? SWOOLE_SOCK_UNIX_STREAM : SWOOLE_SOCK_TCP);
        foreach ($rpcHosts as $rpcHost) {
            $server->listen($rpcHost->host, $rpcHost->port, $rpcHost->isUnixSock ? SWOOLE_SOCK_UNIX_STREAM : SWOOLE_SOCK_TCP);
        }
        $server->on('receive', function (aServer $aServer, int $fd, int $reactorId, string $requestData) use ($rpcHost) {
            try {
                if (! $requestData) {
                    return; // "0"视为ping, 无需处理
                }
                // 鉴权并提取信息
                $clientInfo = $aServer->getClientInfo($fd);
                [$className, $methodName, $arguments] = self::accept([
                    'client_ip' => $clientInfo['remote_ip'],
                ], $requestData, $this->matchRpcHostByServerFd($aServer, $clientInfo['server_fd']));
                // 执行被请求的Rpc方法
                $result = self::execute($className, $methodName, $arguments);
                // 打包Rpc方法的执行结果并将其响应给客户端
                $response = self::package($result);
                $aServer->send($fd, $response);
            } catch (RpcException $exception) {
                echo "RpcException {$exception->getMessage()}\n";
            }
        });
        $server->start();
    }

    /**
     * 根据ServerFd匹配RpcHost
     * @param aServer $server
     * @param int $serverFd
     * @return RpcHost
     */
    private function matchRpcHostByServerFd(aServer $server, int $serverFd): RpcHost {
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
    private function runCoroutineServer() {
        foreach ($this->rpcHosts as $rpcHost) {
            go(function () use($rpcHost) {
                $this->servers[] = $server = new Server(($rpcHost->isUnixSock ? 'unix:' : '') . $rpcHost->host, $rpcHost->port);
                $server->handle(function (Server\Connection $connection) use ($rpcHost) {
                    while (1) {
                        try {
                            $requestData = $connection->recv();
                            $socket = $connection->exportSocket();
                            if (! $requestData) {
                                if ($socket->errCode > 0) {
                                    $connection->close();
                                    echo "{$socket->errMsg}, code: {$socket->errCode}, fd: {$socket->fd}\n";
                                    break;
                                }
                                // 如果是"0", 则为ping
                                continue;
                            }
                            // 鉴权并提取信息
                            [$className, $methodName, $arguments] = self::accept([
                                'client_ip' => $socket->getsockname()['address'],
                            ], $requestData, $rpcHost);
                            // 执行被请求的Rpc方法
                            $result = self::execute($className, $methodName, $arguments);
                            // 打包Rpc方法的执行结果并将其响应给客户端
                            $response = self::package($result);
                            $connection->send($response);
                        } catch (RpcException $exception) {
                            echo "RpcException {$exception->getMessage()}\n";
                        }
                    }
                });
                $server->start();
            });
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
        if ($rpcHost->isUnixSock || '127.0.0.1' === $clientIp) {
            // 凡是本机请求, 无视授权限制
            return;
        } else if ($rpcHost->needNative) {
            throw new RpcException('异常跨域请求, 授权校验失败');
        }
        if ($rpcHost->needLocal) {
            if (! RpcUtility::isLocalIp($clientIp)) {
                throw new RpcException('非法跨域请求, 授权校验失败');
            }
        }
        if ($rpcHost->ipWhiteList) {
            if (! in_array($clientIp, $rpcHost->ipWhiteList)) {
                throw new RpcException('非法请求, 授权校验失败');
            }
        }
        if ($rpcHost->password) {
            if (! RpcUtility::verifyToken($rpcHost->password, $authToken)) {
                throw new RpcException('异常请求, 授权校验失败');
            }
        }
    }

    /**
     * 执行RPC服务并返回结果
     * @param string $className
     * @param string $methodName
     * @param array $arguments
     * @return mixed
     * @throws RpcException
     */
    private static function execute(string $className, string $methodName, array $arguments) {
        if (! is_subclass_of($className, RpcMatrix::class)) {
            throw new RpcException("{$className}非RPC类");
        }
        try {
            $result = call_user_func_array([$className, $methodName], $arguments);
        } catch (Throwable $throwable) {
            // 如果服务端发生了异常, 则抛到客户端
            $result = $throwable;
        }
        return $result;
    }

    /**
     * 打包服务结果
     * @param $result
     * @return string
     * @throws RpcException
     */
    private static function package($result): string {
        if (is_object($result)) {
            $response = [RpcUtility::RESULT_TYPE_OBJECT, serialize($result)];
        } else {
            $response = [RpcUtility::RESULT_TYPE_GENERAL, json_encode($result, JSON_UNESCAPED_UNICODE)];
        }
        $response = RpcUtility::encode(RpcUtility::RESPONSE_FORMATTER, ... $response);
        return $response;
    }
}
