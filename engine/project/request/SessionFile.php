<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-15 18:25
 */

namespace dce\project\request;

use dce\cache\engine\FileCache;

class SessionFile extends Session {
    private static FileCache $cache;

    private function getArrayId(): array|null {
        $id = $this->getId(false);
        return $id ? [$id] : null;
    }

    /** @inheritDoc */
    public function open(Request $request): void {
        if (! isset(self::$cache)) {
            // 直接缓存, 不做按项目实例的支持
            self::$cache = new FileCache(['dir' => $request->config->session['root']]);
        }
        $this->openInit($request);
        // 更新session过期时间
        self::$cache->touch($this->getArrayId());
    }

    /** @inheritDoc */
    public function set(string $key, mixed $value): void {
        $cacheValue = self::$cache->get($this->getArrayId()) ?: [];
        $cacheValue[$key] = $value;
        self::$cache->set($this->getArrayId(), $cacheValue, $request->config->session['ttl'] ?? 3600);
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        $cacheValue = self::$cache->get($this->getArrayId());
        return $cacheValue[$key] ?? false;
    }

    /** @inheritDoc */
    public function getAll(): array {
        return self::$cache->get($this->getArrayId()) ?: [];
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        $cacheValue = self::$cache->get($this->getArrayId()) ?: [];
        if (key_exists($key, $cacheValue)) {
            unset($cacheValue[$key]);
            self::$cache->set($this->getArrayId(), $cacheValue);
        }
    }

    /** @inheritDoc */
    public function destroy(): void {
        self::$cache->del($this->getArrayId());
    }
}