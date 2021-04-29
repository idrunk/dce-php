<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/2/23 7:10
 */

namespace dce\rpc;

use Closure;
use dce\loader\Loader;
use dce\pool\PoolException;
use dce\pool\TcpPool;
use Swoole\Coroutine\Client;
use Throwable;

class RpcClient {
    /** @var float 接收超时时间 */
    private static float $receiveTimeout = 8;

    /** @var array 名字空间/类名包特征映射表 {md5(names / classes): {loaded, hosts : [{host, port, token, max_connection}]}} */
    private static array $namingMapping = [];

    /** @var array 命名空间通配符包与其特征映射表 {name: md5(names)} */
    private static array $wildcardMapping = [];

    /** @var array 类名包与其特征映射表 {class: md5(classes)} */
    private static array $classMapping = [];

    /** @var string 特定主机缓存 */
    private static string $specifiedHost = '';

    /** @var int 特定端口缓存 */
    private static int $specifiedPort = 0;

    /** @var Closure 自动加载处理方法 */
    private static Closure $autoload;

    /**
     * 拦截rpc命名空间的类, 将其转到当前RPC客户端下的魔术方法处理
     * @param array|string $wildcards 监听命名空间集, 如['rpc\*'], 表示用户调用rpc命名空间下的类方法, 如rpc\service\MidService::generation时, 会拦截并转发到Rpc服务器执行
     * @param array $hosts 服务主机列表
     * <pre>
     * {host, port, token}, token不填则表示非加密API, 将不传递token, 此map形式会自动转为下方的数组形式
     * [{host, port, token, max_connection}], 主机集形式, 标准形式
     * </pre>
     * @throws null
     */
    final public static function prepare(array|string $wildcards, array $hosts): void {
        $wildcards = array_map(fn($wildcard) => ltrim($wildcard, '\\'), is_array($wildcards) ? $wildcards : [$wildcards]);
        // 按通配符数组键长度逆序排序, 使得较长的名字空间排在前面, 解决想匹配子级名字空间却因先匹配到父级空间就返回了的问题
        usort($wildcards, fn ($k1, $k2) => strlen($k2) <=> strlen($k1));
        $identity = md5(json_encode($wildcards));
        foreach ($wildcards as $wildcard) {
            if (! key_exists($wildcard, self::$wildcardMapping)) {
                self::$wildcardMapping[$wildcard] = $identity;
                Loader::prepare($wildcard, self::getAutoload());
            } else if (self::$wildcardMapping[$wildcard] !== $identity) {
                throw (new RpcException(RpcException::NAMESPACE_CONFLICT))->format($wildcard);
            }
        }
        self::$namingMapping[$identity] = [
            'loaded' => false,
            'hosts' => RpcUtility::hostsMerge(self::$namingMapping[$identity]['hosts'] ?? [], $hosts),
        ];
    }

    /**
     * 拦截rpc类, 将其转到当前RPC客户端下的魔术方法处理
     * @param array|string $classNames
     * @param array $hosts {host, port, token, max_connection} / [{}]
     * @throws RpcException
     */
    final public static function preload(array|string $classNames, array $hosts): void {
        $classNames = array_map(fn($className) => ltrim($className, '\\'), is_array($classNames) ? $classNames : [$classNames]);
        asort($classNames);
        $identity = md5(json_encode($classNames));
        foreach ($classNames as $className) {
            if (! key_exists($className, self::$classMapping)) {
                self::$classMapping[$className] = $identity;
                Loader::preload($className, self::getAutoload());
            } else if (self::$classMapping[$className] !== $identity) {
                throw (new RpcException(RpcException::CLASS_CONFLICT))->format($className);
            }
        }
        self::$namingMapping[$identity] = [
            'loaded' => false,
            'hosts' => RpcUtility::hostsMerge(self::$namingMapping[$identity]['hosts'] ?? [], $hosts),
        ];
    }

    /**
     * @return Closure
     */
    private static function getAutoload(): Closure {
        if (! isset(self::$autoload)) {
            self::$autoload = function (string $className) {
                self::addClassMapping($className);
                $classNameSplit = explode('\\', $className);
                $baseClassName = array_pop($classNameSplit);
                $namespace = implode('\\', $classNameSplit);
                $parentClass = '\\' . self::class;
                $scripts = "class {$baseClassName} extends {$parentClass} {}";
                if ($namespace) {
                    $scripts = "namespace $namespace { $scripts }";
                }
                try {
                    // 因为callStatic时才会进到这里, 所以classname是绝对合法的, 所以此处不会有安全漏洞
                    eval($scripts);
                } catch (Throwable) {
                    throw (new RpcException(RpcException::INVALID_CLASS))->format($className);
                }
            };
        }
        return self::$autoload;
    }

    /**
     * 将以名字空间注册的类补充进类名映射
     * @param string $className
     * @throws RpcException
     */
    private static function addClassMapping(string $className): void {
        if (key_exists($className, self::$classMapping)) {
            return;
        }
        foreach (self::$wildcardMapping as $wildcard => $identity) {
            if (fnmatch(str_ireplace('\\', '\\\\', $wildcard), $className)) {
                self::$classMapping[$className] = $identity;
                return;
            }
        }
        throw (new RpcException(RpcException::CLASS_NOT_REGISTER))->format($className);
    }

    /**
     * 向特定的服务器请求远程方法, (使用箭头函数将更方便, 如$mid = RpcClient::with(fn() => rpc\service\MidGenerator::generation(), '127.0.0.1', '2333'))
     * @param string $host
     * @param int $port
     * @param callable $callback
     * @return mixed
     */
    final public static function with(string $host, int $port, callable $callback): mixed {
        self::$specifiedHost = $host;
        self::$specifiedPort = $port;
        return call_user_func($callback);
    }

    /**
     * 接管静态方法
     * @param string $methodName
     * @param array $arguments
     * @return mixed
     * @throws RpcException
     * @throws Throwable
     */
    final public static function __callStatic(string $methodName, array $arguments) {
        return self::execute(static::class, $methodName, $arguments);
    }

    /**
     * 执行远程方法
     * @param string $className
     * @param string $methodName
     * @param array $arguments
     * @return mixed
     * @throws RpcException
     * @throws Throwable
     * @throws PoolException
     */
    private static function execute(string $className, string $methodName, array $arguments): mixed {
        $specifiedConfig = [];
        if (self::$specifiedHost) {
            // 如果定义了特定地址, 则拼装地址配置, 供fetch时取指定地址的连接实例, 哈哈哈, 牛逼
            $specifiedConfig = [
                'host' => self::$specifiedHost,
                'port' => self::$specifiedPort,
            ];
            self::$specifiedHost = '';
        }
        $pool = self::getPool($className);
        $client = $pool->fetch($specifiedConfig);
        $data = self::authPack($pool->getProduct($client)->config->token ?? '', $className, $methodName, $arguments);
        $response = self::request($client, $data);
        $result = self::responseHandling($response);
        $pool->put($client);
        return $result;
    }

    /**
     * 取连接池
     * @param string $className
     * @return TcpPool
     */
    private static function getPool(string $className): TcpPool {
        $identity = self::$classMapping[$className];
        ['loaded' => $loaded, 'hosts' => $hosts] = self::$namingMapping[$identity];
        self::$namingMapping[$identity]['loaded'] = true;
        return TcpPool::inst($identity)->setConfigs($hosts, ! $loaded);
    }

    /**
     * 远程请求
     * @param Client $client
     * @param string $data
     * @return string
     * @throws RpcException
     */
    private static function request(Client $client, string $data): string {
        if (! $client->send($data)) {
            throw new RpcException($client->errMsg ?: lang(RpcException::REQUEST_FAILED), $client->errCode);
        }
        $response = $client->recv(self::$receiveTimeout);
        if ($client->errCode > 0) {
            throw new RpcException($client->errMsg ?: RpcException::RESPONSE_TIMEOUT, $client->errCode);
        }
        if (! $response) {
            throw new RpcException(RpcException::EMPTY_RESPONSE);
        }
        return $response;
    }

    /**
     * 数据打包
     * @param string $token
     * @param string $className
     * @param string $methodName
     * @param array $arguments
     * @return string
     * @throws RpcException
     */
    private static function authPack(string $token, string $className, string $methodName, array $arguments): string {
        $data = RpcUtility::encode(RpcUtility::REQUEST_FORMATTER, $token, $className, $methodName, serialize($arguments));
        return $data;
    }

    /**
     * 处理结果
     * @param string $response
     * @return mixed
     * @throws RpcException
     * @throws Throwable
     */
    private static function responseHandling(string $response): mixed {
        [$resultType, $result] = RpcUtility::decode(RpcUtility::RESPONSE_FORMATTER, $response);
        if ($resultType === RpcUtility::RESULT_TYPE_OBJECT) {
            $result = unserialize($result); // 如果结果类型为对象, 则反序列化
            if ($result instanceof Throwable) {
                // 抛出服务端的异常
                throw $result;
            }
        } else {
            $result = json_decode($result, true);
        }
        return $result;
    }
}
