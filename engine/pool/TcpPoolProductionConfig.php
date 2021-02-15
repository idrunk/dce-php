<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/5/7 7:58
 */

namespace dce\pool;

use ArrayAccess;

/**
 * Class TcpPoolProductionConfig
 * @package dce\pool
 */
class TcpPoolProductionConfig extends PoolProductionConfig {
    public string $host;

    public int $port;

    public int $timeout;

    public int $maxConnection;

    private const CAPACITY_DEFAULT = 8;

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
