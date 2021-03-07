<?php
/**
 * Author: Drunk
 * Date: 2017-1-7 17:30
 */

namespace dce\cache;

use dce\Dce;

abstract class Cache {
    protected array $config;

    abstract public function __construct(array $config);

    /**
     * 将key字符串化
     * @param string|array $key
     * @return string
     */
    protected static function genKey(string|array $key): string {
        if (is_array($key)) {
            $key = implode(':', $key);
        } else {
            // 为方便使用, 字符串式key自动加前缀
            $key = Dce::getId() .':'. $key;
        }
        return $key;
    }

    /**
     * 实例化memcache数据库备份缓存
     * (该系列方法待完善, 不建议使用)
     * @return engine\FileCache
     */
    private function getBackupInstance(): engine\FileCache {
        return Dce::$cache->file;
    }

    /**
     * @param string|array $key
     * @return mixed
     */
    protected function backupGet(string|array $key): mixed {
        $meta = $this->getBackupInstance()->getMeta($key);
        if (! $meta) {
            return null;
        }
        $expiry = $meta['expiry'];
        $data = $meta['data'];
        $this->set($key, $data, $expiry); // 如果取到可用缓存, 则将其写入memcache系
        return $data;
    }

    /**
     * @param string|array $key
     * @param mixed $value
     * @param int $expiry
     * @return bool
     */
    protected function backupSet(string|array $key, mixed $value, int $expiry = 0): bool {
        return $this->getBackupInstance()->set($key, $value, $expiry);
    }

    /**
     * @param string|array $key
     * @return bool
     */
    protected function backupDel(string|array $key): bool {
        return $this->getBackupInstance()->del($key);
    }

    /**
     * @param string|array $key
     * @return bool
     */
    abstract public function exists(string|array $key): bool;

    /**
     * @param string|array $key
     * @return mixed
     */
    abstract public function get(string|array $key): mixed;

    /**
     * @param string|array $key
     * @param mixed $value
     * @param int $expiry
     * @return bool
     */
    abstract public function set(string|array $key, mixed $value, int $expiry = 0): bool;

    /**
     * 重置有效时长
     * @param string|array $key
     * @param int $expiry
     * @return bool
     */
    abstract public function touch(string|array $key, int $expiry = 0): bool;

    /**
     * @param string|array $key
     * @param float $value
     * @return int|float|false
     */
    abstract public function inc(string|array $key, float $value = 1): int|float|false;

    /**
     * @param string|array $key
     * @param float $value
     * @return int|float|false
     */
    abstract public function dec(string|array $key, float $value = 1): int|float|false;

    /**
     * @param string|array $key
     * @return bool
     */
    abstract public function del(string|array $key): bool;
}