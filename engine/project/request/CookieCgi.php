<?php
/**
 * Author: Drunk
 * Date: 2019-1-21 21:40
 */

namespace dce\project\request;

class CookieCgi extends Cookie {
    /** @inheritDoc */
    public function get(string $key): mixed {
        return $_COOKIE[$key] ?? null;
    }

    /** @inheritDoc */
    public function set(string $key, $value = "", $expire = 0, $path = "", $domain = "", $secure = false, $httpOnly = false): void {
        setcookie($key, $value, $expire, $path, $domain, $secure, $httpOnly);
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        unset($_COOKIE[$key]);
    }

    /** @inheritDoc */
    public function getAll(string $key): array {
        return $_COOKIE ?? [];
    }
}
