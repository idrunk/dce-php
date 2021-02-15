<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-12 17:54
 */

namespace dce\cache\engine;

use dce\base\SwooleUtility;
use dce\cache\Cache;
use dce\storage\redis\RedisConnector;
use dce\storage\redis\RedisPool;
use Redis;

final class RedisCache extends Cache {
    public function __construct(
        protected array $config
    ) {}

    /** @inheritDoc */
    public function get(array|string $key): mixed {
        $redis = $this->getRedis();
        $value = $redis->get(self::genKey($key));
        $this->putRedis($redis);
        return $value;
    }

    /** @inheritDoc */
    public function set(array|string $key, mixed $value, int $expiry = 0): bool {
        $redis = $this->getRedis();
        $result = $redis->set(self::genKey($key), $value, $expiry);
        $this->putRedis($redis);
        return $result;
    }

    /** @inheritDoc */
    public function inc(array|string $key, float $value = 1): int|float|false {
        $redis = $this->getRedis();
        $result = $redis->decrBy(self::genKey($key), $value);
        $this->putRedis($redis);
        return $result;
    }

    /** @inheritDoc */
    public function dec(array|string $key, float $value = 1): int|float|false {
        $redis = $this->getRedis();
        $result = $redis->decrBy(self::genKey($key), $value);
        $this->putRedis($redis);
        return $result;
    }

    /** @inheritDoc */
    public function del(array|string $key): bool {
        $redis = $this->getRedis();
        $result = $redis->del(self::genKey($key));
        $this->putRedis($redis);
        return !! $result;
    }

    private function getRedis(): Redis {
        if (SwooleUtility::inSwoole()) {
            $redis = RedisPool::inst()->setConfigs($this->config)->fetch();
        } else {
            static $redis;
            if (null === $redis) {
                $redis = (new RedisConnector($this->config))->getRedis();
            }
        }
        return $redis;
    }

    private function putRedis(Redis $redis): void {
        if (SwooleUtility::inSwoole()) {
            RedisPool::inst()->put($redis);
        }
    }
}