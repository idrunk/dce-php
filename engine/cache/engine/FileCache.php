<?php
/**
 * Author: Drunk
 * Date: 2017-1-7 23:11
 */

namespace dce\cache\engine;

use dce\cache\CacheClearable;
use dce\Dce;

class FileCache extends CacheClearable {
    private const EXPIRY_SEPARATOR = "\n";

    private string $dir;

    function __construct(array $config) {
        $this->dir = $config['dir'];
        if(Dce::$initState > 1 && ! is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    public function get(string|array $key): mixed {
        return $this->getClearExpired($key);
    }

    public function set(string|array $key, mixed $value, int $expiry = 0): bool {
        $filename = $this->key2path($key);
        $content = serialize($value);
        $fd = fopen($filename, 'wb');
        flock($fd, LOCK_EX);
        fwrite($fd, $expiry . self::EXPIRY_SEPARATOR . $content);
        flock($fd, LOCK_UN);
        return fclose($fd);
    }

    public function touch(array|string $key, int $expiry = 0): bool {
        $filename = $this->key2path($key);
        return touch($filename);
    }

    // 不安全, 需加锁, 先不管
    public function inc(string|array $key, float $value = 1): int|float|false {
        $value = $this->get($key) + $value;
        $this->set($key, $value);
        return $value;
    }

    public function dec(string|array $key, float $value = 1): int|float|false {
        $value = $this->get($key) - $value;
        $this->set($key, $value);
        return $value;
    }

    public function del(string|array $key): bool {
        $filename = $this->key2path($key);
        return @unlink($filename);
    }

    private function key2path(string|array $path): string {
        $path = self::genKey($path);
        if (! is_file($path)) {
            $path = $this->dir . hash('md5', $path) . '.cache';
        }
        return $path;
    }

    /** @inheritDoc */
    public function listMeta(): array {
        $metas = [];
        foreach (glob($this->dir . '*.cache', GLOB_NOSORT) as $filename) {
            $metas[$filename] = $this->getMeta([$filename]);
        }
        return $metas;
    }

    /** @inheritDoc */
    public function getMeta(string|array $key, bool $loadData = false): array|false {
        $filename = $this->key2path($key);
        if (! is_file($filename)) {
            return false;
        }
        if ($loadData) {
            $content = file_get_contents($filename);
        } else {
            $content = file_get_contents($filename, length: 10);
        }
        $pos = strpos($content, self::EXPIRY_SEPARATOR);
        $expiry = (int) substr($content, 0, $pos);
        if ($loadData) {
            $data = unserialize(substr($content, $pos + 1));
            $createTime = filectime($filename);
        } else {
            $data = null;
            $createTime = null;
        }
        return [
            'data' => $data,
            'expiry' => $expiry,
            'create_time' => $createTime,
            'update_time' => filemtime($filename),
        ];
    }
}
