<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/12 4:48
 */

namespace dce\project\request;

class RawRequestHttpCgi extends RawRequestHttp {
    /** @inheritDoc */
    protected function initProperties(): void {
        $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->isHttps = strtolower($_SERVER["HTTPS"]) === "on";
        $this->host = $_SERVER['HTTP_HOST'];
        $this->requestUri = $_SERVER['REQUEST_URI'];
        $this->queryString = $_SERVER['QUERY_STRING'];
        $this->httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
        $this->remoteAddr = $_SERVER['REMOTE_ADDR'];
        $this->serverPort = $_SERVER['SERVER_PORT'];
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'port' => $_SERVER['REMOTE_PORT'],
        ];
    }

    /**
     * 取原始请求数据
     * @return mixed
     */
    public function getRaw(): array {
        return $_SERVER;
    }

    /** @inheritDoc */
    public function getRawData(): string {
        if (! isset($this->rawData)) {
            $this->rawData = file_get_contents('php://input');
        }
        return $this->rawData;
    }


    /** @inheritDoc */
    public function header(string $key, string $value): void {
        header("{$key}:{$value}");
    }

    /** @inheritDoc */
    public function response (string $content): void {
        echo $content;
    }

    /** @inheritDoc */
    public function export(string $filepath, int $offset = 0, int $length = 0): void {
        $filename = pathinfo($filepath, PATHINFO_FILENAME);
        header("Content-Disposition:filename={$filename}");
        $this->response(file_get_contents($filepath, false, null, $offset, $length));
    }

    /** @inheritDoc */
    public function supplementHttpRequest(Request $request): array {
        // 实例化Session
        $request->cookie = new CookieCgi();
        $request->session = Session::inst($request);
        $request->url = new Url($this);

        // 补充相关请求参数
        $request->files = $_FILES ?? [];
        $request->get = $_GET ?? [];
        $post = $_POST ?? [];
        $post = $this->setPostProperties($request, $post);
        return $post;
    }

    /** @inheritDoc */
    public function redirect(string $jumpUrl, int $jumpCode = 302): void {
        if (! headers_sent()) {
            if ($jumpCode == 301) {
                header('HTTP/1.1 301 Moved Permanently');
            }
            // header("refresh:0;url={$jumpUrl}");
            header('Location: ' . $jumpUrl);
        } else {
            echo "<meta http-equiv='Refresh' content='0;URL={$jumpUrl}'>";
        }
    }

    /** @inheritDoc */
    public function status(int $statusCode, string $reason): void {
        header("HTTP/1.1 {$statusCode} {$reason}");
    }

    /** @inheritDoc */
    public function isAjax(): bool {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest';
    }
}
