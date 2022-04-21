<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/19 3:14
 */

namespace dce\project\session;

use dce\project\request\RequestManager;
use dce\storage\redis\RedisProxy;

class SessionRedis extends Session {
    private const META_KEY = '&meta';
    private const REFERENCE_KEY = 'reference';

    /**
     * 更新session过期时间
     * @param RedisProxy|null $redis
     */
    protected function touch(mixed $redis = null): void {
        $rKey = $this->getId(true);
        // 如果是长存时间，则session需续长存时间，否则需续短存时间
        $meta = $redis->hGet($rKey, self::META_KEY);
        $ttl = ($meta['long_live'] ?? 0) && self::$config['long_ttl'] ? self::$config['long_ttl'] : (self::$config['ttl'] ?: 3600);
        $redis->expire($rKey, $ttl);
        if (
            key_exists(self::REFERENCE_KEY, (array) $meta)
            && ([$requestId, $referenceSid] = $meta[self::REFERENCE_KEY])
            && $requestId !== RequestManager::currentId()
            && $referenceSid !== $this->getId()
        ) {
            unset($meta[self::REFERENCE_KEY]);
            $redis->hSet($rKey, self::META_KEY, $meta);
            self::newBySid($referenceSid)->destroy();
        }
    }

    /** @inheritDoc */
    public function isAlive(): bool {
        return RedisProxy::new(self::$config['index'])->exists($this->getId(true));
    }

    /** @inheritDoc */
    public function set(string $key, mixed $value): void {
        $redis = RedisProxy::new(self::$config['index']);
        $redis->hSet($this->getId(true), $key, $value);
        $this->tryTouch($redis); // 得放在后面touch，否则初始化后就没操作过的session将无法自动过期
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        if (! $sid = $this->getId(true)) return null;
        $redis = RedisProxy::new(self::$config['index']);
        $value = $redis->hGet($sid, $key);
        $this->tryTouch($redis);
        return $value;
    }

    /** @inheritDoc */
    public function getAll(): array {
        if (! $sid = $this->getId(true)) return [];
        $redis = RedisProxy::new(self::$config['index']);
        $value = $redis->hGetAll($sid);
        $this->tryTouch($redis);
        return $value;
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        RedisProxy::new(self::$config['index'])->hDel($this->getId(true), $key);
    }

    /** @inheritDoc */
    public function destroy(): void {
        RedisProxy::new(self::$config['index'])->del($this->getId(true));
    }

    /** @inheritDoc */
    public function renew(bool $longLive = false): static {
        $newSid = self::genId();
        $requestId = RequestManager::currentId();
        $redis = RedisProxy::new(self::$config['index']);
        $data = $redis->hGetAll($this->getId(true));

        if ($data) {
            if ($requestId) {
                $oldMeta = $data[self::META_KEY];
                $oldMeta[self::REFERENCE_KEY] = [$requestId, $newSid];
                $redis->hSet($oldKey = $this->getId(true), self::META_KEY, $oldMeta);
                $redis->expire($oldKey, self::$oldTtl);
            } else {
                $this->destroy();
            }
        }

        $data[self::META_KEY] = ['create_time' => time(), 'long_live' => $longLive];
        $requestId && $data && $data[self::META_KEY][self::REFERENCE_KEY] = [$requestId, $this->getId()];

        $this->setId($newSid);
        $redis->hMSet($this->getId(true), $data);
        $this->touched = false;
        $this->tryTouch($redis);

        return $this;
    }

    /** @inheritDoc */
    public function getMeta(): array {
        $redis = RedisProxy::new(self::$config['index']);
        $rKey = $this->getId(true);
        $meta = $redis->hGet($rKey, self::META_KEY) ?: [];
        $meta += ['expiry' => ($meta['long_live'] ?? 0) ? self::$config['long_ttl'] : self::$config['ttl'], 'ttl' => $redis->ttl($rKey)];
        return $meta;
    }
}