<?php
/**
 * Author: Drunk
 * Date: 2020-04-09 18:55
 */

namespace dce\project\request;

use dce\config\ConfigManager;
use dce\Dce;
use dce\project\node\Node;
use dce\project\ProjectManager;
use dce\service\server\RawRequestConnection;
use JetBrains\PhpStorm\ArrayShape;

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
     * @return array
     */
    #[ArrayShape(['ip' => 'string', 'port' => 'int', 'request' => 'string'])]
    abstract public function getClientInfo(): array;

    /**
     * 取原始请求提交数据
     * @return string|string[]
     */
    public function getRawData(): string|array {
        return $this->rawData;
    }

    protected function logRequest(Node $node): void {
        if (DCE_CLI_MODE && ConfigManager::getProjectConfig(ProjectManager::get($node->projectName))->log['access']['request']) {
            $requestData = is_string($this->getRawData()) ? $this->getRawData() : json_encode($this->getRawData(), JSON_UNESCAPED_UNICODE);
            echo sprintf("[%s] (%s) %s\n\n\n", date('Y-m-d H:i:s'), $this->getClientInfo()['request'], $requestData ? "\n\n$requestData" : '');
        }
    }

    protected function logResponse(mixed $data): void {
        if (DCE_CLI_MODE && RequestManager::current()->project->getConfig()->log['access']['response']) {
            $responseData = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
            echo sprintf("[%s] (响应 %s) %s\n\n\n", date('Y-m-d H:i:s'), $this->getClientInfo()['request'], $responseData ? "\n\n$responseData" : '');
        }
    }
}
