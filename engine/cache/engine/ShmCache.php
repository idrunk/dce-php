<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/1/29 11:07
 */

namespace dce\cache\engine;

use dce\base\SwooleUtility;
use dce\cache\CacheClearable;
use Swoole\Table;

final class ShmCache extends CacheClearable {
    private Table $table;

    public function __construct(array $config) {
        if (SwooleUtility::inSwoole()) {
            SwooleUtility::rootProcessConstraint();
            $this->table = new Table(1024);
            // 最大支持存 1M
            $this->table->column('data', Table::TYPE_STRING, 1048576);
            $this->table->column('expiry', Table::TYPE_INT, 4);
            $this->table->column('create_time', Table::TYPE_INT, 8);
            $this->table->column('update_time', Table::TYPE_INT, 8);
            $this->table->create();
        }
    }

    /** @inheritDoc */
    public function get(string|array $key): mixed {
        return $this->getClearExpired($key);
    }

    /** @inheritDoc */
    public function set(string|array $key, mixed $value, int $expiry = 0): bool {
        $key = self::genKey($key);
        $time = time();
        $value = serialize($value);
        $data = [
            'data' => $value,
            'expiry' => $expiry,
            'create_time' => $this->getMeta($key)['create_time'] ?? $time,
            'update_time' => $time,
        ];
        return $this->table->set($key, $data);
    }

    /** @inheritDoc */
    public function touch(array|string $key, int $expiry = 0): bool {
        $key = self::genKey($key);
        return $this->table->set($key, ['update_time' => time()]);
    }

    /** @inheritDoc */
    public function inc(string|array $key, float $value = 1): int|float|false {
        $key = self::genKey($key);
        return $this->table->incr($key, 'data', $value);
    }

    /** @inheritDoc */
    public function dec(string|array $key, float $value = 1): int|float|false {
        $key = self::genKey($key);
        return $this->table->decr($key, 'data', $value);
    }

    /** @inheritDoc */
    public function del(string|array $key): bool {
        $key = self::genKey($key);
        return $this->table->del($key);
    }

    /** @inheritDoc */
    public function listMeta(): array {
        $metas = [];
        foreach ($this->table as $k => $v) {
            $metas[$k] = self::packMeta($v);
        }
        return $metas;
    }

    /** @inheritDoc */
    public function getMeta(array|string $key, bool $loadData = false): array|false {
        $key = self::genKey($key);
        if (! $this->table->exists($key)) {
            return false;
        }
        $meta = $this->table->get($key);
        return self::packMeta($meta, $loadData);
    }

    private static function packMeta(array $meta, bool $loadData = false): array {
        $meta['data'] = $loadData ? unserialize($meta['data']) : null;
        return $meta;
    }
}