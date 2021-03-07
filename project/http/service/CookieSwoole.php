<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/19 2:15
 */

namespace http\service;

use dce\project\request\Cookie;

class CookieSwoole extends Cookie {
    private RawRequestHttpSwoole $rawRequest;

    public function __construct(RawRequestHttpSwoole $requestHttpSwoole) {
        $this->rawRequest = $requestHttpSwoole;
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        return $this->rawRequest->getRaw()->cookie[$key] ?? null;
    }

    /** @inheritDoc */
    public function set(string $key, string $value = "", int $expire = 0, string $path = "", string $domain = "", bool $secure = false, bool $httpOnly = false): void {
        $this->rawRequest->getResponse()->cookie($key, $value, $expire, $path, $domain, $secure, $httpOnly);
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        $this->rawRequest->getResponse()->cookie($key, null);
    }

    /** @inheritDoc */
    public function getAll(string $key): array {
        return $this->rawRequest->getRaw()->cookie ?? [];
    }
}