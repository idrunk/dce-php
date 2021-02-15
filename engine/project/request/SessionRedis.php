<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/19 3:14
 */

namespace dce\project\request;

use dce\storage\redis\DceRedis;

class SessionRedis extends Session {
    /** @inheritDoc */
    public function open(Request $request): void {
        $this->openInit($request);
        $redis = DceRedis::get();
        // 更新session过期时间
        $redis->expire($this->getId(), $request->config->session['ttl'] ?? 3600);
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function set(string $key, mixed $value): void {
        $redis = DceRedis::get();
        $redis->hSet($this->getId(), $key, $value);
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        $redis = DceRedis::get();
        $value = $redis->hGet($this->getId(), $key);
        DceRedis::put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function getAll(): array {
        $redis = DceRedis::get();
        $value = $redis->hGetAll($this->getId());
        DceRedis::put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function delete(string $key): void {
        $redis = DceRedis::get();
        $redis->hDel($this->getId(), $key);
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function destroy(): void {
        $redis = DceRedis::get();
        $redis->del($this->getId());
        DceRedis::put($redis);
    }
}