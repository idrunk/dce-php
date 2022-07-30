<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2022/6/25 20:11
 */

namespace dce\db\active;

use dce\Dce;
use dce\model\Model;
use dce\storage\redis\RedisProxy;

class CacheEngine {
    /**
     * @param array $data
     * @param class-string<Model> $modelClass
     * @return array
     */
    public static function encodeKeys(array $data, string $modelClass): array {
        return array_reduce(array_keys($data), fn($m, $k) => $m + [$modelClass::getProperty($k)->id, $data[$k]], []);
    }

    /**
     * @param array $data
     * @param class-string<Model> $modelClass
     * @return array
     */
    public static function decodeKeys(array $data, string $modelClass): array {
        return array_reduce(array_keys($data), fn($m, $k) => $m + [$modelClass::getPropertyById($k)->name => $data[$k]], []);
    }

    public static function save(string $cacheKey, array $data): void {
        $redis = RedisProxy::new(Dce::$config->cache['redis']['index'], true);
        $redis->hMSet($cacheKey, $data);
        $redis->expire($cacheKey, 604800);
    }

    public static function load(string $cacheKey): array {
        return RedisProxy::new(Dce::$config->cache['redis']['index'], true)->hGetAll($cacheKey);
    }

    public static function delete(string $cacheKey): void {
        RedisProxy::new(Dce::$config->cache['redis']['index'])->del($cacheKey);
    }
}