<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-15 17:59
 */

namespace dce\storage\redis;

use dce\base\SwooleUtility;
use dce\Dce;
use Redis;

final class DceRedis {
    /**
     * 判断Redis是否可用
     * @return bool
     */
    public static function isAvailable(): bool {
        return isset(Dce::$config->redis['host']);
    }

    /**
     * 取Redis实例, 根据环境判断从连接池取或直接实例化一个
     * @param int $index
     * @param bool $noSerialize
     * @return Redis
     * @throws \dce\pool\PoolException
     */
    public static function get(int $index = -1, bool $noSerialize = false): Redis {
        if (SwooleUtility::inSwoole()) {
            $redis = RedisPool::inst()->setConfigs(Dce::$config->redis)->fetch();
        } else {
            static $redis;
            if (null === $redis) {
                $redis = (new RedisConnector(Dce::$config->redis))->getRedis();
            }
        }
        if ($index > -1 && $index != Dce::$config->redis['index']) {
            $redis->select($index);
        }
        if ($noSerialize) {
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        }
        return $redis;
    }

    /**
     * 将取的实例放回连接池
     * @param Redis $redis
     */
    public static function put(Redis $redis): void {
        if (Dce::$config->redis['index'] !== $redis->getDbNum()) {
            // 如果修改了默认库, 则还原选库
            $redis->select(Dce::$config->redis['index']);
        }
        if ($redis->getOption(Redis::OPT_SERIALIZER) === Redis::SERIALIZER_NONE) {
            // 如果设置为了不编码, 则重置为自动编码
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        }
        if (SwooleUtility::inSwoole()) {
            RedisPool::inst()->put($redis);
        }
    }
}