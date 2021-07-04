<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-15 23:31
 */

namespace dce\sharding\id_generator\server;

use dce\sharding\id_generator\bridge\IdgBatch;
use dce\sharding\id_generator\bridge\IdgStorage;
use dce\sharding\id_generator\IdgException;

/**
 * Drunk ID生成器服务端
 * @package didg
 */
final class IdgServer {
    use IdgServerProducer;

    /**
     * IdgServer constructor.
     * @param IdgStorage $storage
     * @param string $configDir
     */
    private function __construct(
        private IdgStorage $storage,
        private string $configDir
    ) {}

    /**
     * 取单例实例
     * @param IdgStorage $storage  储存器
     * @param string $configDir    配置文件所在目录
     * @return static
     */
    public static function new(IdgStorage $storage, string $configDir): self {
        static $inst;
        if (null === $inst) {
            $inst = new self($storage, $configDir);
        }
        return $inst;
    }

    /**
     * 客户端Tag注册
     * @param string $tag
     * @return IdgBatch
     * @throws IdgException
     */
    public function register(string $tag): IdgBatch {
        $batch = $this->dataInit($tag);
        if ('time' === $batch->type) {
            $this->produceTime($batch);
        } else {
            $this->produceIncrement($batch);
        };
        $this->storage->save($tag, $batch);
        return $batch;
    }

    /**
     * 生成指定Tag ID池
     * @param string $tag
     * @return IdgBatch
     * @throws IdgException
     */
    public function generate(string $tag): IdgBatch {
        $batch = $this->register($tag);
        return IdgBatch::new()->setProperties(['batch_from' => $batch->batchFrom, 'batch_to' => $batch->batchTo]
            + (isset($batch->timeId) ? ['time_id' => $batch->timeId] : []));
    }

    /**
     * Tag数据初始化
     * @param string $tag
     * @return IdgBatch
     * @throws IdgException
     */
    private function dataInit(string $tag): IdgBatch {
        $batch = $this->storage->load($tag);
        if (! $batch) {
            $batch = $this->getBatch($tag);
            $this->storage->save($tag, $batch);
        }
        return $batch;
    }

    /**
     * 根据配置实例化批次实例
     * @param string $tag
     * @return IdgBatch
     * @throws IdgException
     */
    private function getBatch(string $tag): IdgBatch {
        static $configs = [];
        if (! key_exists($tag, $configs)) {
            $configPath = $this->configDir . "/{$tag}.php";
            if (! is_file($configPath)) {
                throw (new IdgException(IdgException::CONFIG_ITEM_MISSING))->format($configPath);
            }
            $configs[$tag] = IdgBatch::new()->setProperties(include($configPath));
            if (! in_array($configs[$tag]->type ?? 0, [IdgBatch::TYPE_INCREMENT, IdgBatch::TYPE_TIME])) {
                throw (new IdgException(IdgException::PROPERTY_MAY_TYPE_ERROR))->format($configPath);
            }
        }
        return $configs[$tag];
    }
}
