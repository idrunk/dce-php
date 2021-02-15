<?php
/**
 * Author: Drunk
 * Date: 2019/10/22 15:39
 */

namespace dce\db\connector;

use ArrayAccess;
use dce\pool\PoolProductionConfig;

class DbPoolProductionConfig extends PoolProductionConfig {
    /** @var string 主机地址 */
    public string $host;

    /** @var string 数据库用户名 */
    public string $dbUser;

    /** @var string 数据库密码 */
    public string $dbPassword;

    /** @var string 数据库名 */
    public string $dbName;

    /** @var int 数据库端口 */
    public int $dbPort;

    /** @var int 默认连接池容量 */
    private const DEFAULT_MAX_CONNECTION = 8;

    /**
     * DbPoolProductionConfig constructor.
     * @param array|ArrayAccess $config
     */
    public function __construct(array|ArrayAccess $config) {
        parent::__construct($config, $config['max_connection'] ?? $config['maxConnection'] ?? self::DEFAULT_MAX_CONNECTION);
    }

    /**
     * @param array|ArrayAccess $config
     * @return bool
     */
    public function match(array|ArrayAccess $config): bool {
        return $this->matchWithProperties($config, ['host', 'dbName']);
    }
}
