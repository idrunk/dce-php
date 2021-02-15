<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/18 2:58
 */

namespace http\service;

use dce\project\request\RawRequestHttp;
use dce\project\request\Request;
use dce\project\request\SessionRedis;
use dce\project\request\Url;
use Swoole\Http\Request as RequestSwoole;
use Swoole\Http\Response as ResponseSwoole;

class RawRequestHttpSwoole extends RawRequestHttp {
    private RequestSwoole $requestSwoole;

    private ResponseSwoole $responseSwoole;

    public function __construct(RequestSwoole $requestSwoole, ResponseSwoole $responseSwoole) {
        $this->requestSwoole = $requestSwoole;
        $this->responseSwoole = $responseSwoole;
    }

    /** @inheritDoc */
    protected function initProperties(): void {
        $this->method = strtolower($this->requestSwoole->server['request_method']);
        $this->isHttps = 0; // todo
        $this->host = $this->requestSwoole->header['host'];
        $this->requestUri = $this->requestSwoole->server['request_uri'];
        $this->queryString = $this->requestSwoole->server['query_string'] ?? '';
        $this->httpOrigin = $this->requestSwoole->header['origin'] ?? '';
        $this->userAgent = $this->requestSwoole->header['user-agent'];
        $this->remoteAddr = $this->requestSwoole->server['remote_addr'];
        $this->serverPort = $this->requestSwoole->server['server_port'];
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        return [
            'ip' => $this->requestSwoole['remote_addr'],
            'port' => $this->requestSwoole['remote_port'],
        ];
    }

    /** @inheritDoc */
    public function getRawData(): string {
        if (! isset($this->rawData)) {
            $this->rawData = $this->requestSwoole->rawContent();
        }
        return $this->rawData;
    }

    /** @inheritDoc */
    public function getRaw(): RequestSwoole {
        return $this->requestSwoole;
    }

    /**
     * @return ResponseSwoole
     */
    public function getResponse(): ResponseSwoole {
        return $this->responseSwoole;
    }

    /** @inheritDoc */
    public function header(string $key, string $value): void {
        $this->responseSwoole->header($key, $value);
    }

    /** @inheritDoc */
    public function response(string $content): void {
        $this->responseSwoole->end($content);
    }

    /** @inheritDoc */
    public function export(string $filepath, int $offset = 0, int $length = 0): void {
        $this->responseSwoole->sendfile($filepath, $offset, $length);
    }

    /** @inheritDoc */
    protected function supplementHttpRequest(Request $request): array {
        $request->cookie = new CookieSwoole($this);
        $request->session = new SessionRedis();
        $request->session->openByRequest($request);
        $request->url = new Url($this);// 补充相关请求参数

        // 补充相关请求参数
        $request->files = $this->requestSwoole->files ?? [];
        $request->get = $this->requestSwoole->get ?? [];
        $post = $this->requestSwoole->post ?? [];
        $post = $this->setPostProperties($request, $post);
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
