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

class RedisPool extends Pool {
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
}
