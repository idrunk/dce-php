<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/19 3:14
 */

namespace dce\project\request;

use dce\Dce;
use dce\storage\redis\RedisPool;

class SessionRedis extends Session {
    /** @inheritDoc */
    public function open(Request $request): void {
        if (! $this->getId()) {
            $id = $request->cookie->get(self::getSidName());
            if (! $id) {
                $id = self::getSidName() . '-' . Dce::getId() . ':' . sha1(uniqid('', true));
                $request->cookie->set(self::getSidName(), $id);
            }
            $this->setId($id);
        }
        $redis = RedisPool::inst()->setConfigs(Dce::$config->redis)->fetch();
        // 更新session过期时间
        $redis->expire($this->getId(), $request->config->session['ttl'] ?? 3600);
        RedisPool::inst()->put($redis);
    }

    /** @inheritDoc */
    public function set(string $key, $value): void {
        $redis = RedisPool::inst()->fetch();
        $redis->hSet($this->getId(), $key, $value);
        RedisPool::inst()->put($redis);
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        $redis = RedisPool::inst()->fetch();
        $value = $redis->hGet($this->getId(), $key);
        RedisPool::inst()->put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function getAll(): array {
        $redis = RedisPool::inst()->fetch();
        $value = $redis->hGetAll($this->getId());
        RedisPool::inst()->put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        $redis = RedisPool::inst()->fetch();
        $redis->hDel($this->getId(), $key);
        RedisPool::inst()->put($redis);
    }

    /** @inheritDoc */
    public function destroy(): void {
        $redis = RedisPool::inst()->fetch();
        $redis->del($this->getId());
        RedisPool::inst()->put($redis);
    }
}