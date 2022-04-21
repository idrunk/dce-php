<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-12-16 20:59
 */

namespace dce\storage\redis;

use dce\Dce;

class RedisProxySimple extends RedisProxy {
    protected function __construct(int $index, bool $noSerialize) {
        static $redis;
        $this->redis = $redis ??= (new RedisConnector(Dce::$config->redis))->getRedis();
        parent::__construct($index, $noSerialize);
    }
}