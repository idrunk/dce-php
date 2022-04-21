<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/3/5 18:59
 */

namespace dce\sharding\id_generator\bridge;

use dce\storage\redis\RedisProxy;

class IdgStorageRedis extends IdgStorage {
    private string $prefix;

    private int $index;

    public function __construct(string $prefix, int $index) {
        $this->prefix = $prefix;
        $this->index = $index;
    }

    /** @inheritDoc */
    protected function genKey(string $tag, string $prefix = ''): string {
        return "$prefix$this->prefix:$tag";
    }

    /** @inheritDoc */
    public function load(string $tag): IdgBatch|null {
        return RedisProxy::new($this->index)->get($this->genKey($tag)) ?: null;
    }

    /** @inheritDoc */
    public function save(string $tag, IdgBatch $batch): void {
        RedisProxy::new($this->index)->set($this->genKey($tag), $batch);
    }
}
