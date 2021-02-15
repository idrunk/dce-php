<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/5/6 22:15
 */

namespace dce\pool;

use Swoole\Coroutine\Client;

class TcpPool extends Pool {
    /** @inheritDoc */
    protected function produce(PoolProductionConfig $config): Client {
        $connection = new Client(($config->port ?? 0) < 1 ? SWOOLE_SOCK_UNIX_STREAM : SWOOLE_SOCK_TCP);
        if (! $connection->connect($config->host, $config->port ?? 0, $config->timeout ?? -1)) {
            throw new PoolException($connection->errMsg, $connection->errCode);
        }
        return $connection;
    }

    /** @inheritDoc */
    public function fetch(array $config = []): Client {
        return $this->get($config);
    }

    /**
     * 取池子实例
     * @param string ...$identities
     * @return static
     */
    public static function inst(string ... $identities): static {
        return parent::getInstance(TcpPoolProductionConfig::class, ... $identities);
    }
}
