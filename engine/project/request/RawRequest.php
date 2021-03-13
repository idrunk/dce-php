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

    /** @var mixed 原始请求提交的数据 */
    protected mixed $rawData;

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
     * @return array
     */
    abstract public function getClientInfo(): array;

    /**
     * 取原始请求提交数据
     * @return mixed
     */
    public function getRawData(): mixed {
        return $this->rawData;
    }
}
