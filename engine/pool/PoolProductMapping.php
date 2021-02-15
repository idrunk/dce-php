<?php
/**
 * Author: Drunk
 * Date: 2020-04-02 16:02
 */

namespace dce\pool;

/**
 * 实例池实例映射表
 * Class InstanceMapping
 * @package dce\pool
 */
class PoolProductMapping {
    private const TIMEOUT_DURATION = 180;

    private Pool $pool;

    private array $mapping = [];

    public function __construct(Pool $pool) {
        $this->pool = $pool;
    }

    /**
     * 根据实例取映射表
     * @param object $object
     * @return array
     * @throws PoolException
     */
    public function getMap(object $object): array {
        $id = spl_object_id($object);
        if (! isset($this->mapping[$id])) {
            throw new PoolException('目标实例非当前有效池实例, 无法获取映射表');
        }
        return $this->mapping[$id];
    }

    /**
     * 映射标记
     * @param object $object
     * @param PoolProductionConfig $config
     * @param ChannelAbstract $channel
     */
    public function mark(object $object, PoolProductionConfig $config, ChannelAbstract $channel): void {
        $id = spl_object_id($object);
        if (! key_exists($id, $this->mapping)) {
            $this->mapping[$id] = [
                'object' => $object,
                'config' => $config,
                'channel' => $channel,
                'last_fetch_time' => time(),
            ];
        }
    }

    /**
     * 更新实例最后取出时间
     * @param object $object
     */
    public function update(object $object): void {
        $id = spl_object_id($object);
        $this->mapping[$id]['last_fetch_time'] = time();
    }

    /**
     * 主动移除实例
     * @param object $object
     */
    public function remove(object $object): void {
        $id = spl_object_id($object);
        unset($this->mapping[$id]);
    }

    /**
     * 清除超时没回收的实例
     * @param PoolProductionConfig $config
     */
    public function clear(PoolProductionConfig $config): void {
        $time = time();
        foreach ($this->mapping as $id => $item) {
            if ($config === $item['config'] && $time - $item['last_fetch_time'] >= self::TIMEOUT_DURATION) {
                $this->pool->destroyProduct($item['object']);
                // 销毁实例减产量
                $item['config']->destroy();
                // 销毁实例退销量, (因为只有在生产消费率与生产率满时才进到此, 所以必定是有消费了未退还的实例, 所以此处可以安全退还)
                $item['config']->return();
                // 销毁实例相关映射
                unset($this->mapping[$id]);
            }
        }
    }
}
