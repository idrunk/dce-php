<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-15 18:25
 */

namespace dce\project\session;

use dce\cache\engine\FileCache;

class SessionFile extends Session {
    private static FileCache $cache;

    protected function __construct() {
        if (! isset(self::$cache)) {
            // 直接缓存, 不做按项目实例的支持
            self::$cache = new FileCache(['dir' => self::$config['root']]);
        }
    }

    private function getArrayId(): array|null {
        $id = $this->getId();
        return $id ? [$id] : null;
    }

    /**
     * @inheritDoc
     */
    protected function touch(mixed $param1 = null): void {
        // 更新session过期时间
        self::$cache->touch($this->getArrayId());
    }

    /**
     * @inheritDoc
     */
    public function isAlive(): bool {
        return self::$cache->exists($this->getArrayId());
    }

    /** @inheritDoc */
    public function set(string $key, mixed $value): void {
        $this->tryTouch();
        $cacheValue = self::$cache->get($this->getArrayId()) ?: [];
        $cacheValue[$key] = $value;
        self::$cache->set($this->getArrayId(), $cacheValue, $request->config->session['ttl'] ?? 3600);
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        $this->tryTouch();
        $cacheValue = self::$cache->get($this->getArrayId());
        return $cacheValue[$key] ?? false;
    }

    /** @inheritDoc */
    public function getAll(): array {
        $this->tryTouch();
        return self::$cache->get($this->getArrayId()) ?: [];
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        $this->tryTouch();
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