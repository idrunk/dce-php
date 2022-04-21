<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/3/8 19:47
 */

namespace dce\storage\redis;

use dce\pool\PoolProductionConfig;
use dce\pool\Pool;
use dce\pool\PoolException;
use Redis;
use RedisException;
use Throwable;

class RedisPool extends Pool {
    private const REGEXP_CONNECTION_LOST = '/Connection\s+lost|Operation\s+timed\s+out|error\s+on\s+connection/i';

    /**
     * @param PoolProductionConfig $config
     * @return Redis
     * @throws PoolException
     */
    protected function produce(PoolProductionConfig $config): Redis {
        return (new RedisConnector($config, false))->getRedis();
    }

    public function fetch(): Redis {
        return $this->get();
    }

    public static function inst(string ... $identities): static {
        return parent::getInstance(RedisPoolProductionConfig::class, ... $identities);
    }

    protected function retryable(Throwable $throwable): bool {
        return $throwable instanceof RedisException && preg_match(self::REGEXP_CONNECTION_LOST, $throwable->getMessage());
    }
}
