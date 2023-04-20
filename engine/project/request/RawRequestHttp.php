<?php
/**
 * Author: Drunk
 * Date: 2020-04-15 17:01
 */

namespace dce\project\request;

use dce\base\QuietException;
use dce\project\node\Node;
use dce\project\node\NodeManager;
use drunk\Network;
use Throwable;

abstract class RawRequestHttp extends RawRequest {
    public const METHOD_OPTIONS = 'options';
    public const METHOD_HEAD = 'head';

    public bool $isHttps;

    public string $host;

    public string $queryString;

    public string $requestUri;

    public string $httpOrigin;

    public string $userAgent;

    public string $referer;

    public string $remoteAddr;

    public int $serverPort;

    private array $locatedArguments = [];

    private bool $pathInQueryString;

    /**
     * 取请求头信息
     * @param string|null $key {string: 某个头, null: 全部头信息}
     * @return string|array|null
     */
    abstract public function getHeader(string|null $key = null): string|array|null;

    /**
     * 设置响应头
     * @param string $key
     * @param string $value
     */
    abstract public function header(string $key, string $value): void;

    /**
     * 响应Http请求
     * @param string $content
     */
    abstract public function response(string $content): void;

    /**
     * 导出文件
     * @param string $filepath
     * @param int $offset
     * @param int $length
     */
    abstract public function export(string $filepath, int $offset = 0, int $length = 0): void;

    /**
     * 对象属性初始化
     */
    abstract protected function initProperties(): void;

    /**
     * 补充Request对象
     * @param Request $request
     * @return array
     */
    abstract protected function supplementHttpRequest(Request $request): array;

    /**
     * 状态码响应
     * @param int $statusCode
     * @param string $reason
     */
    abstract public function status(int $statusCode, string $reason): void;

    /**
     * 重定向
     * @param string $jumpUrl
     * @param int $jumpCode
     */
    abstract public function redirect(string $jumpUrl, int $jumpCode = 302): void;

    /**
     * 判断是否Ajax请求
     * @return bool
     */
    abstract public function isAjax(): bool;

    /** @inheritDoc */
    final public function init(): void {
        $this->initProperties();
        $this->path = $this->getPath();
    }

    /**
     * 生成Http请求路径 (处理主机项目绑定时自动补全省略的项目路径部分)
     * @return string
     */
    protected function getPath(): string {
        $this->pathInQueryString = str_starts_with($this->queryString, '/');
        $path = $this->pathInQueryString ? $this->queryString : $this->requestUri;
        $nodeTree = NodeManager::getTreeByHost($this->host, $this->serverPort);
        if ($nodeTree) {
            $slash = str_starts_with($path, '/') ? '/' : '';
            if (! str_starts_with($path, "{$slash}{$nodeTree->projectName}"))
                $path = "{$nodeTree->projectName}{$path}";
        }
        return $path;
    }

    /** @inheritDoc */
    public function routeGetNode(): Node {
        try {
            $router = new Router($this); // 取路由定位到的当前节点及其上级节点ID集
            $nodeIdFamily = $router->getLocatedNodeIdFamily();
            // 取从请求地址中解析出来的参数
            $this->locatedArguments = $router->getLocatedArguments();
            $this->remainingPaths = $router->getComponentsRemaining();
            // 当前节点赋值
            $node = NodeManager::getNode(end($nodeIdFamily));
        } catch (Throwable $throwable) {
            ! in_array($this->path, ['', '/']) && throw $throwable;

            if ($this->isAjax()) {
                $node = NodeManager::getTreeByPath('dce/empty/http/ajax')->getFirstNode();
            } else {
                $node = NodeManager::getTreeByPath('dce/empty/http')->getFirstNode();
            }
        }
        return $node;
    }

    /**
     * 补充请求对象
     * @param Request $request
     */
    final public function supplementRequest(Request $request): void {
        // 处理重定向
        $jumpUrl = $request->node->http301 ?? $request->node->http302 ?? 0;
        if ($jumpUrl) {
            // 如果节点配置了重定向, 则重定向
            $jumpCode = ($request->node->http301 ?? 0) ? 301 : 302;
            $this->redirect($jumpUrl, $jumpCode);
            die;
        }

        // 处理跨域
        $this->handleCors($request);

        // 补充Http请求参数
        $post = $this->supplementHttpRequest($request);
        // 删除Dce节点路径信息相关Url参数
        if ($this->pathInQueryString)
            unset($request->get[key($request->get)]);
        $request->get = $this->locatedArguments + $request->get;
        $request->request = $request->get + $post;
        if (isset($this->rawData)) $request->rawData = $this->rawData;
    }

    /**
     * 设置Post属性
     * @param Request $request
     * @param array $post
     * @return array
     */
    protected function setPostProperties(Request $request, array $post): array {
        if ($post) {
            $request->post = $post;
        } else if ('get' !== $this->method) {
            $post = $this->parseRawData() ?? [];
            match ($this->method) {
                'put' => $request->put = $post,
                'patch' => $request->patch = $post,
                default => $request->post = $post,
            };
        }
        return $post;
    }

    /**
     * 解析原始数据为参数表, (子类可覆盖此方法自定义解析方法)
     * @return array|null
     */
    protected function parseRawData(): array|null {
        $rawData = $this->getRawData();
        return json_decode($rawData, true);
    }

    /**
     * 处理跨域
     * @param Request $request
     * @throws QuietException
     */
    private function handleCors(Request $request): void {
        if ($corsOrigins = $request->node->corsOrigins ?? []) {
            // 若配置了跨域白名单，则允许所有本地地址跨域
            if (Network::isLocalIp($this->getClientInfo()['ip'])) $corsOrigins = ['*'];
            $this->header('Access-Control-Allow-Origin', implode(',', $corsOrigins));
            $this->header('Access-Control-Allow-Credentials', 'true');
            $this->header('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS');
            $this->header('Access-Control-Allow-Headers', 'X-Requested-With, X-Session-Id, Content-Type, Authorization, Accept, Cookie, Origin, Referer, UserToken, ReferToken');
            $this->header('Access-Control-Max-Age', '7200');
            if($this->method === self::METHOD_OPTIONS) {
                $this->response('*_^');
                throw new QuietException(0);
            }
        }
    }

    /** @inheritDoc */
    public function getServerInfo(): array {
        return [
            'host' => $this->host,
            'port' => $this->serverPort,
        ];
    }
}
