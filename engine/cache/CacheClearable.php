<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/1/29 14:16
 */

namespace dce\cache;

use JetBrains\PhpStorm\ArrayShape;

abstract class CacheClearable extends Cache {
    /**
     * 清除过期缓存
     * @param string|array $key
     * @return mixed
     */
    protected function getClearExpired(string|array $key): mixed {
        $time = time();
        $meta = $this->getMeta($key, true);
        if ($meta && $meta['expiry'] > 0 && $meta['expiry'] + $meta['update_time'] < $time) {
            $meta = null;
            $this->del($key);
            // 如果当前的过期了, 则尝试清除其他过期的
            foreach ($this->listMeta() as $key => $data) {
                if ($data['expiry'] > 0 && $data['expiry'] + $data['update_time'] < $time) {
                    $this->del([$key]);
                }
            }
        }
        return $meta['data'] ?? false;
    }

    /**
     * 列出缓存元
     * @return array
     */
    abstract public function listMeta(): array;

    /**
     * 取缓存元信息
     * @param string|array $key
     * @param bool $loadData
     * @return array|false
     */
    #[ArrayShape([
        'data' => 'mixed',
        'expiry' => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
    ])]
    abstract public function getMeta(string|array $key, bool $loadData = false): array|false;
}