<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-12-16 20:59
 */

namespace dce\storage\redis;

use dce\db\connector\PdoDbConnector;
use dce\Dce;
use Swoole\Coroutine;
use Swoole\Coroutine\Barrier;

class RedisProxyPool extends RedisProxy {
    private static RedisPool|null $pool;

    protected function __construct(int $index, bool $noSerialize) {
        self::$pool ??= RedisPool::inst()->setConfigs(Dce::$config->redis);
        $this->redis = self::$pool->fetch();

        $barrier = Barrier::make();
        $exceptions = [];
        PdoDbConnector::registerCoroutineAutoReleaseOrHandle(
            self::$pool->retryableContainer(function() use($index, $noSerialize) {
                parent::__construct($index, $noSerialize);
                PdoDbConnector::registerCoroutineAutoReleaseOrHandle(Coroutine::getCid(), false, 'redis');
            }, $exceptions, $barrier), type: 'redis'
        );
        Barrier::wait($barrier);
        if ($exceptions) {
            self::$pool = null;
            throw array_pop($exceptions);
        }
    }

    public function __destruct() {
        $redis = $this->redis;
        parent::__destruct();
        // 必须最后put，否则高并发下，put回了pool但redis属性未释放，可能导致下述异常：
        // Socket#76 has already been bound to another coroutine#19, reading of the same socket in coroutine#24 at the same time is not allowed
        self::$pool?->put($redis);
    }
}