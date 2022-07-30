<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-04-17 17:13
 */

namespace dce\project\request;

use dce\base\SwooleUtility;
use Swoole\Coroutine;
use Throwable;
use WeakReference;

class RequestManager {
    /** @var int 每处理多少请求后清理一次过期映射 */
    private const CLEAR_MOD = 128;

    /** @var WeakReference<Request>[] 请求映射表 */
    private static array $requestMapping = [];

    /**
     * 实例化请求器并路由
     * @param class-string<RawRequest> $rawRequestClass
     * @param mixed ...$rawRequestParams
     * @return Request
     * @throws RequestException
     * @throws Throwable
     */
    public static function route(string $rawRequestClass, mixed ... $rawRequestParams): Request {
        $rawRequest = new $rawRequestClass(... $rawRequestParams);
        $rawRequest->init();
        $request = new Request($rawRequest);
        self::logMapping($request);
        $request->route();
        return $request;
    }

    /** 尝试清理过期请求的映射记录 */
    private static function logMapping(Request $request): void {
        self::$requestMapping[$request->id] = WeakReference::create($request);
        // 每隔一定轮数清理一次过期请求的残留映射记录
        if (! (count(self::$requestMapping) % self::CLEAR_MOD)) {
            foreach (self::$requestMapping as $k => $requestRef)
                if (! $requestRef->get()) unset(self::$requestMapping[$k]);
        }
    }

    /**
     * 取当前请求ID
     * - CGI模式时, 一个进程处理一个请求, 控制器间不会干扰, 无需为请求分配唯一ID, 返回0
     * - 非携程模式时, 请求间不会并行执行控制器, 也不会互相干扰, 无需唯一ID, 返回0或-1
     * - Swoole协程模式时, 请求控制器会并行执行, 可能会相互干扰产生污染, 但他们拥有不同的根协程ID, 可以以此作为其ID隔离出安全沙盒
     * @return int
     */
    public static function currentId(): int {
        $requestId = 0;
        if (SwooleUtility::inSwoole() && ($requestId = Coroutine::getCid()) > 0) {
            while (($pcid = Coroutine::getPcid($requestId)) > 0)
                $requestId = $pcid;
        }
        return $requestId;
    }

    /**
     * 取当前的请求对象
     * @return Request|null
     */
    public static function current(): Request|null {
        return ($requestRef = self::$requestMapping[self::currentId()] ?? null) ? $requestRef->get() : null;
    }
}