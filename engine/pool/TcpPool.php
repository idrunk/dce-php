<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/5/6 22:15
 */

namespace dce\pool;

use Swoole\Coroutine\Client;
use Swoole\Timer;

class TcpPool extends Pool {
    private array $tickMapping = [];

    /** @inheritDoc */
    protected function produce(PoolProductionConfig $config): Client {
        $connection = new Client(($config->port ?? 0) < 1 ? SWOOLE_SOCK_UNIX_STREAM : SWOOLE_SOCK_TCP);
        if (! $connection->connect($config->host, $config->port ?? 0, $config->timeout ?? -1)) {
            throw new PoolException($connection->errMsg, $connection->errCode);
        }
        $this->initTick($connection);
        return $connection;
    }

    private function initTick(Client $connection): void {
        $this->tickMapping[spl_object_id($connection)] = Timer::tick(30000, function() use($connection) {
            $lastFetchTime = $this->getProduct($connection)->lastFetch ?? 0;
            if (time() - $lastFetchTime >= 28) {
                // 如果到了闹铃点, 且距上次取连接已过了闹铃间隔时间(未过则不必发, 因为取连接则肯定用来send过业务数据了, send数据能覆盖ping功能), 则发送ping包
                $connection->send(0);
            }
        });
    }

    public function destroyProduct(object $object): void {
        $objId = spl_object_id($object);
        Timer::clear($this->tickMapping[$objId]);
        unset($this->tickMapping[$objId]);
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
