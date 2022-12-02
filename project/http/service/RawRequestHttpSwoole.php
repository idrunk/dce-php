<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/18 2:58
 */

namespace http\service;

use dce\log\LogManager;
use dce\project\request\RawRequestHttp;
use dce\project\request\Request;
use dce\project\session\Session;
use dce\project\request\Url;
use Swoole\Http\Request as RequestSwoole;
use Swoole\Http\Response as ResponseSwoole;
use websocket\service\WebsocketServer;

class RawRequestHttpSwoole extends RawRequestHttp {
    public function __construct(
        private HttpServer|WebsocketServer $httpServer,
        private RequestSwoole $requestSwoole,
        private ResponseSwoole $responseSwoole,
    ) {}

    /**
     * 取Http Server
     * @return HttpServer|WebsocketServer
     */
    public function getServer(): HttpServer|WebsocketServer {
        return $this->httpServer;
    }

    /** @inheritDoc */
    protected function initProperties(): void {
        $this->method = strtolower($this->requestSwoole->server['request_method']);
        // Swoole Http Server原生未提供判断依据, 且连接可能是经过nginx转发的, 转发时可能会丢掉这个信息, 所以这里无法准确获取是否https
        // 因为这个属性在Dce中依赖度不高, 仅在Url->getCurrent时用到, 所以用了这个可能成立的条件判断, 就算错了也影响不大
        // 还有一种方法判断, 那就是利用Server->getClientInfo方法, 返回值里面有ssl_client_cert属性则为Https, 但因为感觉为了取这个无关紧要又不一定对的属性, 而花费未知的资源消耗成本, 有点划不来, 就没实现
        $header = $this->requestSwoole->header;
        $this->isHttps = $this->requestSwoole->server['server_port'] === 443 || $this->requestSwoole->server['server_protocol'] === 'HTTP/2';
        $this->host = $header['host'];
        $this->requestUri = $this->requestSwoole->server['request_uri'];
        $this->queryString = urldecode($this->requestSwoole->server['query_string'] ?? '');
        $this->httpOrigin = $header['origin'] ?? '';
        $this->userAgent = $header['user-agent'];
        $this->remoteAddr = $header['remote-addr'] ?? $header['x-real-ip'] ?? $this->getServer()->getServer()->getClientInfo($this->requestSwoole->fd)['remote_ip'] ?? '-';
        $this->serverPort = $this->requestSwoole->server['server_port'];
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        $header = $this->requestSwoole->header;
        $header['request'] = "$this->method $this->host$this->requestUri?$this->queryString";
        $header['ip'] = $this->remoteAddr;
        $header['port'] = $header['remote-port'] ?? $header['x-real-port'] ?? $this->getServer()->getServer()->getClientInfo($this->requestSwoole->fd)['remote_port'] ?? 0;
        $header['server_port'] = $this->serverPort;
        return $header;
    }

    /** @inheritDoc */
    public function getRawData(): string {
        $this->rawData ??= $this->requestSwoole->rawContent();
        return $this->rawData;
    }

    /** @inheritDoc */
    public function getRaw(): RequestSwoole {
        return $this->requestSwoole;
    }

    /** @return ResponseSwoole */
    public function getResponse(): ResponseSwoole {
        return $this->responseSwoole;
    }

    /** @inheritDoc */
    public function getHeader(string|null $key = null): string|array|null {
        return ! $key ? $this->requestSwoole->header : $this->requestSwoole->header[$key] ?? null;
    }

    /** @inheritDoc */
    public function header(string $key, string $value): void {
        $this->responseSwoole->header($key, $value);
    }

    /** @inheritDoc */
    public function response(string $content): void {
        LogManager::response($this, $content);
        $this->responseSwoole->end($content);
    }

    /** @inheritDoc */
    public function export(string $filepath, int $offset = 0, int $length = 0): void {
        $this->responseSwoole->sendfile($filepath, $offset, $length);
    }

    /** @inheritDoc */
    protected function supplementHttpRequest(Request $request): array {
        // 补充相关请求参数
        $request->files = $this->requestSwoole->files ?? [];
        $request->get = $this->requestSwoole->get ?? [];
        $post = $this->setPostProperties($request, $this->requestSwoole->post ?? []);
        $request->cookie = new CookieSwoole($this);
        $request->session = Session::newByRequest($request);
        $request->url = new Url($this); // 补充相关请求参数
        return $post;
    }

    /** @inheritDoc */
    public function redirect(string $jumpUrl, int $jumpCode = 302): void {
        $this->responseSwoole->redirect($jumpUrl, $jumpCode);
    }

    /** @inheritDoc */
    public function status(int $statusCode, string $reason): void {
        $this->responseSwoole->status($statusCode, $reason);
    }

    /** @inheritDoc */
    public function isAjax(): bool {
        return strtolower($this->getRaw()->header['x-requested-with'] ?? '') === 'xmlhttprequest';
    }
}
