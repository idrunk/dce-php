<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/3/5 18:59
 */

namespace dce\sharding\id_generator\bridge;

use dce\Dce;
use dce\storage\redis\RedisPool;

class IdgStorageRedis extends IdgStorage {
    private array $configs;

    private string $prefix;

    public function __construct(string $prefix) {
        $this->configs = Dce::$config->redis;
        $this->prefix = $prefix;
    }

    /** @inheritDoc */
    protected function genKey(string $tag, string $prefix = ''): string {
        return "{$prefix}{$this->prefix}:{$tag}";
    }

    /** @inheritDoc */
    public function load(string $tag): IdgBatch|null {
        $redis = RedisPool::inst()->setConfigs($this->configs, false)->fetch();
        $batch = $redis->get($this->genKey($tag));
        RedisPool::inst()->put($redis);
        return $batch ?: null;
    }

    /** @inheritDoc */
    public function save(string $tag, IdgBatch $batch): void {
        $redis = RedisPool::inst()->fetch();
        $redis->set($this->genKey($tag), $batch);
        RedisPool::inst()->put($redis);
    }
}
