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
    public static function get(): Redis {
        if (SwooleUtility::inSwoole()) {
            $redis = RedisPool::inst()->setConfigs(Dce::$config->redis)->fetch();
        } else {
            static $redis;
            if (null === $redis) {
                $redis = (new RedisConnector(Dce::$config->redis))->getRedis();
            }
        }
        return $redis;
    }

    public static function put(Redis $redis): void {
        if (SwooleUtility::inSwoole()) {
            RedisPool::inst()->put($redis);
        }
    }
}