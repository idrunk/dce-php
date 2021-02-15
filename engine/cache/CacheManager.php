<?php
/**
 * Author: Drunk
 * Date: 2017-1-8 2:16
 */

namespace dce\cache;

use dce\base\SwooleUtility;
use dce\cache\engine\FileCache;
use dce\cache\engine\MemcacheCache;
use dce\cache\engine\MemcachedCache;
use dce\cache\engine\RedisCache;
use dce\cache\engine\ShmCache;
use dce\cache\engine\VarCache;
use dce\Dce;

final class CacheManager {
    /** @var FileCache 本地文件缓存 */
    public FileCache $file;

    /** @var RedisCache Redis缓存 */
    public RedisCache $redis;

    /** @var MemcacheCache Memcache缓存 */
    public MemcacheCache $memcache;

    /** @var MemcachedCache Memcached缓存 */
    public MemcachedCache $memcached;

    /** @var VarCache 静态变量缓存 */
    public VarCache $var;

    /** @var ShmCache 共享内存缓存 */
    public ShmCache $shm;

    /** @var Cache 默认缓存 */
    public Cache $default;

    /** @var Cache 共享内存或默认缓存 */
    public Cache $shmDefault;

    private function __construct() {
        $this->initFileCache();
        $this->initRedisCache();
        $this->initMemcacheCache();
        $this->initMemcachedCache();
        $this->initVarCache();
        $this->initShmCache();
        $this->default = match (Dce::$config->cache['default']) {
            'redis' => $this->redis,
            'memcache' => $this->memcache,
            'memcached' => $this->memcached,
            default => $this->file,
        };
        $this->shmDefault = SwooleUtility::inSwoole() ? $this->shm : $this->default;
    }

    private function initFileCache(): void {
        $this->file = new FileCache(Dce::$config->cache['file']);
    }

    private function initRedisCache(): void {
        $this->redis = new RedisCache(Dce::$config->redis ?? []);
    }

    private function initMemcacheCache(): void {
        $this->memcache = new MemcacheCache(Dce::$config->cache['memcache'] ?? []);
    }

    private function initMemcachedCache(): void {
        $this->memcached = new MemcachedCache(Dce::$config->cache['memcached'] ?? []);
    }

    private function initVarCache(): void {
        $this->var = new VarCache([]);
    }

    private function initShmCache(): void {
        $this->shm = new ShmCache([]);
    }

    public function get(string|array $key): mixed {
        return $this->default->get($key);
    }

    public function set(string|array $key, mixed $value, int $expiry = 0): bool {
        return $this->default->set($key, $value, $expiry);
    }

    public function touch(string|array $key, int $expiry = 0): bool {
        return $this->default->touch($key, $expiry);
    }

    public function inc(string|array $key, float $value = 1): int|float|false {
        return $this->default->inc($key, $value);
    }

    public function dec(string|array $key, float $value = 1): int|float|false {
        return $this->default->dec($key, $value);
    }

    public function del(string|array $key): bool {
        return $this->default->del($key);
    }

    public static function init(): self {
        static $instance;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * 判断文件集是否被改动过
     * @param array $files
     * @return bool
     */
    public static function fileIsModified(array $files): bool {
        sort($files);
        $mtimeArray = [];
        foreach ($files as $filename) {
            if (is_file($filename)) {
                $mtimeArray[] = filemtime($filename);
            }
        }
        $filenameCrc32 = hash('md5', implode('|', $files));
        $fileVersionOriginal = Dce::$cache->get($filenameCrc32);
        $fileVersion = hash('md5', implode('|', $mtimeArray));
        if ($fileVersion === $fileVersionOriginal) {
            return false;
        }
        return Dce::$cache->set($filenameCrc32, $fileVersion) || 1;
    }
}
