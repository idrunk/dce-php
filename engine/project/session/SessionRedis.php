<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/19 3:14
 */

namespace dce\project\session;

use dce\storage\redis\DceRedis;
use Redis;

class SessionRedis extends Session {
    private const META_KEY = '&meta';

    /**
     * 更新session过期时间
     * @param Redis|null $redis
     */
    protected function touch(mixed $redis = null): void {
        $rKey = $this->getId(true);
        // 如果是长存时间，则session需续长存时间，否则需续短存时间
        $ttl = ($redis->hGet($rKey, self::META_KEY)['long_live'] ?? 0) && self::$config['long_ttl'] ? self::$config['long_ttl'] : (self::$config['ttl'] ?: 3600);
        $redis->expire($rKey, $ttl);
    }

    /** @inheritDoc */
    public function isAlive(): bool {
        $redis = DceRedis::get(self::$config['index']);
        $result = $redis->exists($this->getId(true));
        DceRedis::put($redis);
        return $result;
    }

    /** @inheritDoc */
    public function set(string $key, mixed $value): void {
        $redis = DceRedis::get(self::$config['index']);
        $this->tryTouch($redis);
        $redis->hSet($this->getId(true), $key, $value);
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        $redis = DceRedis::get(self::$config['index']);
        $this->tryTouch($redis);
        $value = $redis->hGet($this->getId(true), $key);
        DceRedis::put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function getAll(): array {
        $redis = DceRedis::get(self::$config['index']);
        $this->tryTouch($redis);
        $value = $redis->hGetAll($this->getId(true));
        DceRedis::put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        $redis = DceRedis::get(self::$config['index']);
        $this->tryTouch($redis);
        $redis->hDel($this->getId(true), $key);
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function destroy(): void {
        $redis = DceRedis::get(self::$config['index']);
        $redis->del($this->getId(true));
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function renew(bool $longLive = false): static {
        $data = $this->getAll();
        $data[self::META_KEY] = ['create_time' => time(), 'long_live' => $longLive];
        $this->destroy();
        $this->setId(self::genId());
        $redis = DceRedis::get(self::$config['index']);
        $redis->hMSet($this->getId(true), $data);
        DceRedis::put($redis);
        return $this;
    }

    /** @inheritDoc */
    public function getMeta(): array {
        $redis = DceRedis::get(self::$config['index']);
        $rKey = $this->getId(true);
        $meta = $redis->hGet($rKey, self::META_KEY) ?: [];
        $meta += ['expiry' => ($meta['long_live'] ?? 0) ? self::$config['long_ttl'] : self::$config['ttl'], 'ttl' => $redis->ttl($rKey)];
        DceRedis::put($redis);
        return $meta;
    }
}