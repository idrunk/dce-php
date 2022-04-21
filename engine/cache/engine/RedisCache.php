<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-12 17:54
 */

namespace dce\cache\engine;

use dce\cache\Cache;
use dce\Dce;
use dce\storage\redis\RedisProxy;

final class RedisCache extends Cache {
    public function __construct(
        protected array $config
    ) {}

    /** @inheritDoc */
    public function get(array|string $key): mixed {
        return RedisProxy::new($this->config['index'])->get(self::genKey($key));
    }

    /** @inheritDoc */
    public function exists(string|array $key): bool {
        return RedisProxy::new($this->config['index'])->exists(self::genKey($key));
    }

    /** @inheritDoc */
    public function set(array|string $key, mixed $value, int $expiry = 0): bool {
        return RedisProxy::new($this->config['index'])->set(self::genKey($key), $value, $expiry);
    }

    public function touch(array|string $key, int $expiry = 0): bool {
        return RedisProxy::new($this->config['index'])->expire(self::genKey($key), $expiry);
    }

    /** @inheritDoc */
    public function inc(array|string $key, float $value = 1): int|float|false {
        return RedisProxy::new($this->config['index'])->incrBy(self::genKey($key), $value);
    }

    /** @inheritDoc */
    public function dec(array|string $key, float $value = 1): int|float|false {
        return RedisProxy::new($this->config['index'])->decrBy(self::genKey($key), $value);
    }

    /** @inheritDoc */
    public function del(array|string $key): bool {
        return !! RedisProxy::new($this->config['index'])->del(self::genKey($key));
    }

    /** @inheritDoc */
    public function clear(): void {
        $redis = RedisProxy::new($this->config['index']);
        // 直接删掉和当前主机应用绑定的, 几乎都是缓存, 非缓存的全服型数据不会与应用ID绑定
        foreach ($redis->keys(Dce::getId() . ':*') as $key) $redis->del($key);
    }
}