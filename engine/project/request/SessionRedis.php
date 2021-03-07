<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/19 3:14
 */

namespace dce\project\request;

use dce\storage\redis\DceRedis;

class SessionRedis extends Session {
    /** @inheritDoc */
    protected function touch(mixed $param1 = null): void {
        // 更新session过期时间
        $param1->expire($this->getId(true), self::$config['ttl']);
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
}