<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-19 1:42
 */

namespace dce\sharding\id_generator\bridge;

use dce\Dce;

abstract class IdgStorage {
    /**
     * 加进程锁
     * @param string $tag
     * @return bool
     */
    public function lock(string $tag): bool {
        return Dce::$lock->procLock($this->genKey($tag, 'didg:'));
    }

    /**
     * 解进程锁
     * @param string $tag
     */
    public function unlock(string $tag): void {
        Dce::$lock->procUnlock($this->genKey($tag, 'didg:'));
    }

    /**
     * 生成缓存键/文件名
     * @param string $tag
     * @param string $prefix
     * @return string
     */
    abstract protected function genKey(string $tag, string $prefix = ''): string;

    /**
     * 加载配置数据
     * @param string $tag
     * @return IdgBatch|null
     */
    abstract public function load(string $tag): IdgBatch|null;

    /**
     * 储存配置数据
     * @param string $tag
     * @param IdgBatch $batch
     */
    abstract public function save(string $tag, IdgBatch $batch): void;
}
