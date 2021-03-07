<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-12 17:54
 */

namespace dce\cache\engine;

use dce\cache\Cache;
use dce\storage\redis\DceRedis;

final class RedisCache extends Cache {
    public function __construct(
        protected array $config
    ) {}

    /** @inheritDoc */
    public function get(array|string $key): mixed {
        $redis = DceRedis::get($this->config['index']);
        $value = $redis->get(self::genKey($key));
        DceRedis::put($redis);
        return $value;
    }

    /** @inheritDoc */
    public function exists(string|array $key): bool {
        $redis = DceRedis::get($this->config['index']);
        $result = $redis->exists(self::genKey($key));
        DceRedis::put($redis);
        return $result;
    }

    /** @inheritDoc */
    public function set(array|string $key, mixed $value, int $expiry = 0): bool {
        $redis = DceRedis::get($this->config['index']);
        $result = $redis->set(self::genKey($key), $value, $expiry);
        DceRedis::put($redis);
        return $result;
    }

    public function touch(array|string $key, int $expiry = 0): bool {
        $redis = DceRedis::get($this->config['index']);
        $result = $redis->expire(self::genKey($key), $expiry);
        DceRedis::put($redis);
        return $result;
    }

    /** @inheritDoc */
    public function inc(array|string $key, float $value = 1): int|float|false {
        $redis = DceRedis::get($this->config['index']);
        $result = $redis->decrBy(self::genKey($key), $value);
        DceRedis::put($redis);
        return $result;
    }

    /** @inheritDoc */
    public function dec(array|string $key, float $value = 1): int|float|false {
        $redis = DceRedis::get($this->config['index']);
        $result = $redis->decrBy(self::genKey($key), $value);
        DceRedis::put($redis);
        return $result;
    }

    /** @inheritDoc */
    public function del(array|string $key): bool {
        $redis = DceRedis::get($this->config['index']);
        $result = $redis->del(self::genKey($key));
        DceRedis::put($redis);
        return !! $result;
    }
}