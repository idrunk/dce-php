<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/3/8 19:52
 */

namespace dce\storage\redis;

use ArrayAccess;
use dce\pool\PoolProductionConfig;

/**
 * Class RedisConfig
 * @package dce\storage\redis
 */
class RedisPoolProductionConfig extends PoolProductionConfig {
    public string $host;

    public int $port;

    public string $token;

    private const CAPACITY_DEFAULT = 16;

    /**
     * DbPoolProductionConfig constructor.
     * @param array|ArrayAccess $config
     */
    public function __construct(array|ArrayAccess $config) {
        parent::__construct($config, intval($config['max_connection'] ?? 0) ?: self::CAPACITY_DEFAULT);
    }

    /**
     * @param array|ArrayAccess $config
     * @return bool
     */
    public function match(array|ArrayAccess $config): bool {
        return $this->matchWithProperties($config, ['host', 'port']);
    }
}
