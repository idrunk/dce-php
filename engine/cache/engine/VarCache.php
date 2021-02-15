<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/1/29 11:07
 */

namespace dce\cache\engine;

use dce\cache\CacheClearable;

final class VarCache extends CacheClearable {
    private static array $cacheMapping = [];

    public function __construct(array $config) {}

    /** @inheritDoc */
    public function get(string|array $key): mixed {
        return $this->getClearExpired($key);
    }

    /** @inheritDoc */
    public function set(string|array $key, mixed $value, int $expiry = 0): bool {
        $key = self::genKey($key);
        $time = time();
        if (! key_exists($key, self::$cacheMapping)) {
            self::$cacheMapping[$key] = [ 'create_time' => $time, ];
        }
        self::$cacheMapping[$key]['data'] = $value;
        self::$cacheMapping[$key]['expiry'] = $expiry;
        self::$cacheMapping[$key]['update_time'] = $time;
        return true;
    }

    /** @inheritDoc */
    public function touch(array|string $key, int $expiry = 0): bool {
        $key = self::genKey($key);
        self::$cacheMapping[$key]['update_time'] = time();
        return true;
    }

    /** @inheritDoc */
    public function inc(string|array $key, float $value = 1): int|float|false {
        $key = self::genKey($key);
        return self::$cacheMapping[$key]['data'] += $value;
    }

    /** @inheritDoc */
    public function dec(string|array $key, float $value = 1): int|float|false {
        $key = self::genKey($key);
        return self::$cacheMapping[$key]['data'] -= $value;
    }

    /** @inheritDoc */
    public function del(string|array $key): bool {
        $key = self::genKey($key);
        unset(self::$cacheMapping[$key]);
        return true;
    }

    /** @inheritDoc */
    public function listMeta(): array {
        return self::$cacheMapping;
    }

    /** @inheritDoc */
    public function getMeta(array|string $key, bool $loadData = false): array|false {
        $key = self::genKey($key);
        return self::$cacheMapping[$key] ?? false;
    }
}