<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/19 3:14
 */

namespace dce\project\session;

use dce\project\request\RequestManager;
use dce\storage\redis\DceRedis;
use Redis;

class SessionRedis extends Session {
    private const MetaKey = '&meta';
    private const ReferenceKey = 'reference';

    /**
     * 更新session过期时间
     * @param Redis|null $redis
     */
    protected function touch(mixed $redis = null): void {
        $rKey = $this->getId(true);
        // 如果是长存时间，则session需续长存时间，否则需续短存时间
        $meta = $redis->hGet($rKey, self::MetaKey);
        $ttl = ($meta['long_live'] ?? 0) && self::$config['long_ttl'] ? self::$config['long_ttl'] : (self::$config['ttl'] ?: 3600);
        $redis->expire($rKey, $ttl);
        if (
            key_exists(self::ReferenceKey, (array) $meta)
            && ([$requestId, $referenceSid] = $meta[self::ReferenceKey])
            && $requestId !== RequestManager::currentId()
            && $referenceSid !== $this->getId()
        ) {
            unset($meta[self::ReferenceKey]);
            $redis->hSet($rKey, self::MetaKey, $meta);
            self::newBySid($referenceSid)->destroy();
        }
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
        $redis->hSet($this->getId(true), $key, $value);
        $this->tryTouch($redis); // 得放在后面touch，否则初始化后就没操作过的session将无法自动过期
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        $redis = DceRedis::get(self::$config['index']);
        $value = $redis->hGet($this->getId(true), $key);
        $this->tryTouch($redis);
        DceRedis::put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function getAll(): array {
        $redis = DceRedis::get(self::$config['index']);
        $value = $redis->hGetAll($this->getId(true));
        $this->tryTouch($redis);
        DceRedis::put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        $redis = DceRedis::get(self::$config['index']);
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
        $newSid = self::genId();
        $requestId = RequestManager::currentId();
        $redis = DceRedis::get(self::$config['index']);
        $data = $redis->hGetAll($this->getId(true));

        if ($data) {
            if ($requestId) {
                $oldMeta = $data[self::MetaKey];
                $oldMeta[self::ReferenceKey] = [$requestId, $newSid];
                $redis->hSet($oldKey = $this->getId(true), self::MetaKey, $oldMeta);
                $redis->expire($oldKey, self::$oldTtl);
            } else {
                $this->destroy();
            }
        }

        $data[self::MetaKey] = ['create_time' => time(), 'long_live' => $longLive];
        $requestId && $data && $data[self::MetaKey][self::ReferenceKey] = [$requestId, $this->getId()];

        $this->setId($newSid);
        $redis->hMSet($this->getId(true), $data);
        $this->touched = false;
        $this->tryTouch($redis);

        DceRedis::put($redis);
        return $this;
    }

    /** @inheritDoc */
    public function getMeta(): array {
        $redis = DceRedis::get(self::$config['index']);
        $rKey = $this->getId(true);
        $meta = $redis->hGet($rKey, self::MetaKey) ?: [];
        $meta += ['expiry' => ($meta['long_live'] ?? 0) ? self::$config['long_ttl'] : self::$config['ttl'], 'ttl' => $redis->ttl($rKey)];
        DceRedis::put($redis);
        return $meta;
    }
}