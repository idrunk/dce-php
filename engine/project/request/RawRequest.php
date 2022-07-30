<?php
/**
 * Author: Drunk
 * Date: 2020-04-09 18:55
 */

namespace dce\project\request;

use dce\project\node\Node;

abstract class RawRequest {
    /** @var string 请求方式 */
    public string $method;

    /** @var string 请求路径 */
    public string $path;

    /** @var array 惰性路由匹配时剩余未被匹配的路径组件集 */
    public array $remainingPaths = [];

    /** @var string|string[] 原始请求提交的数据 */
    protected string|array $rawData;

    /**
     * 取原始请求信息
     * @return mixed
     */
    abstract public function getRaw(): mixed;

    /**
     * 初始化rawRequest属性值
     */
    abstract public function init(): void;

    /**
     * 路由并取节点
     * @return Node
     */
    abstract public function routeGetNode(): Node;

    /**
     * 补充Request, 完善其属性
     * @param Request $request
     */
    abstract public function supplementRequest(Request $request): void;

    /**
     * 取客户端信息, {ip, port}
     * @return array{ip: string, port: int, request: string}
     */
    abstract public function getClientInfo(): array;

    /**
     * 取服务端信息, {host, port}
     * @return array{host: string, port: int}
     */
    abstract public function getServerInfo(): array;

    /**
     * 取原始请求提交数据
     * @return string|string[]
     */
    public function getRawData(): string|array {
        return $this->rawData;
    }
}
