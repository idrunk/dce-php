<?php
/**
 * Author: Drunk
 * Date: 2020-04-02 16:02
 */

namespace dce\pool;

use dce\base\TraitModel;
use WeakReference;

/**
 * 实例池实例映射表
 * Class InstanceMapping
 * @package dce\pool
 */
class PoolProduct {
    use TraitModel;

    public WeakReference $productRef;

    public PoolProductionConfig $config;

    public ChannelAbstract $channel;

    public int $lastFetch;

    /** @var self[] */
    private array $mapping = [];

    /**
     * 根据实例取映射表
     * @param object $object
     * @return self
     * @throws PoolException
     */
    public function get(object $object): self {
        $id = spl_object_id($object);
        if (! isset($this->mapping[$id])) {
            throw new PoolException(PoolException::INVALID_CHANNEL_INSTANCE);
        }
        return $this->mapping[$id];
    }

    /**
     * 映射标记
     * @param object $object
     * @param PoolProductionConfig $config
     * @param ChannelAbstract $channel
     */
    public function set(object $object, PoolProductionConfig $config, ChannelAbstract $channel): void {
        $id = spl_object_id($object);
        if (! key_exists($id, $this->mapping)) {
            $this->mapping[$id] = self::new()->setProperties([
                'product_ref' => WeakReference::create($object),
                'config' => $config,
                'channel' => $channel,
            ]);
        }
    }

    /**
     * 更新实例最后取出时间
     * @param object $object
     * @throws PoolException
     */
    public function refresh(object $object): void {
        $this->get($object)->lastFetch = time();
    }

    /**
     * 清除失效没回收的实例
     * @param PoolProductionConfig $config
     */
    public function clear(PoolProductionConfig $config): void {
        foreach ($this->mapping as $id => $item) {
            if ($config === $item->config && ! $item->productRef->get()) {
                // 销毁实例减产量
                $item->config->destroy();
                // 销毁实例退销量, (因为只有在生产消费率与生产率满时才进到此, 所以必定是有消费了未退还的实例, 所以此处可以安全退还)
                $item->config->return();
                // 销毁实例相关映射
                unset($this->mapping[$id]);
            }
        }
    }
}
