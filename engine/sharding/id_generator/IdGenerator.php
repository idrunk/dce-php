<?php
/**
 * Author: Drunk (idrunk.net drunkce.com)
 * Date: 2020-12-14 14:55
 */

namespace dce\sharding\id_generator;

use dce\sharding\id_generator\bridge\IdgBatch;
use dce\sharding\id_generator\bridge\IdgRequestInterface;
use dce\sharding\id_generator\bridge\IdgStorage;
use dce\sharding\id_generator\client\IdgClient;
use dce\sharding\id_generator\client\IdgClientIncrement;
use dce\sharding\id_generator\client\IdgClientTime;

final class IdGenerator {
    /** @var IdgClient[] */
    private array $clientMapping = [];

    private function __construct(
        private IdgStorage $storage,
        private IdgRequestInterface $request
    ) {}

    /**
     * 取同参单例对象
     * @param IdgStorage $storage
     * @param IdgRequestInterface $request
     * @return static
     */
    public static function new(IdgStorage $storage, IdgRequestInterface $request): self {
        static $mapping = [];
        $key = sprintf('%s_%s', spl_object_id($storage), spl_object_id($request));
        if (! key_exists($key, $mapping)) {
            $mapping[$key] = new self($storage, $request);
        }
        return $mapping[$key];
    }

    /**
     * 是否加载过
     * @param string $tag
     * @return bool
     */
    public function wasLoaded(string $tag): bool {
        return key_exists($tag, $this->clientMapping);
    }

    /**
     * 取基础配置
     * @param string $tag
     * @return IdgBatch
     * @throws IdgException
     */
    public function getBatch(string $tag): IdgBatch {
        $batch = $this->getLock($tag)->getBatch();
        unset($batch->batchFrom, $batch->batchTo, $batch->timeId);
        return $batch;
    }

    /**
     * 分解ID (从ID取组成部分,[server_id, modulo_id, batch_id, time_id])
     * @param string $tag
     * @param int $id
     * @return IdgBatch
     * @throws IdgException
     */
    public function parse(string $tag, int $id): IdgBatch {
        return $this->getLock($tag)->parse($id);
    }

    /**
     * 根据ID提取分库基因ModuloId
     * @param string $tag
     * @param int $id
     * @param int $modulus 模数, 若传入了则执行取模运算计算余数
     * @return int
     * @throws IdgException
     */
    public function extractGene(string $tag, int $id, int $modulus = 0): int {
        return $this->getLock($tag)->extractGene($id, $modulus);
    }

    /**
     * 生成ID
     * @param string $tag
     * @param int|string $uid 用户ID, 若为字符串, 则会自动转为crc32的int
     * @return int|array
     * @throws IdgException
     */
    public function generate(string $tag, int|string $uid = 0): int|array {
        $this->storage->lock($tag);
        $client = $this->get($tag);
        // 设计缓存批次的概念以提升ID生成性能, 仅当缓存批次无效或过期时才从客户端储存申请新的缓存批次
        if (! $client->validCacheBatch()) {
            $batch = $this->storage->load($tag);
            $client->setBatch($batch)->applyCacheBatch();
            $this->storage->save($tag, $batch);
        }
        $result = $client->generate($uid);
        $this->storage->unlock($tag);
        return $result;
    }

    /**
     * HashId生成器 (可用于生成SessionId)
     * @param string $tag
     * @param string $algo  哈希算法, 默认sha1
     * @return string
     * @throws IdgException
     */
    public function generateHash(string $tag, string $algo = 'sha1'): string {
        return hash($algo, $this->generate($tag) . mt_rand());
    }

    /**
     * 批量生成ID
     * @param string $tag
     * @param int $count
     * @param int|string $uid
     * @return int[]
     * @throws IdgException
     */
    public function batchGenerate(string $tag, int $count, int|string $uid = 0): array {
        $this->storage->lock($tag);
        $client = $this->get($tag);
        $batch = $this->storage->load($tag);
        $result = $client->setBatch($batch)->batchGenerate($count, $uid);
        $this->storage->save($tag, $batch);
        $this->storage->unlock($tag);
        return $result;
    }

    /**
     * 取 $tag 对应的单例 IdgClient 实例
     * @param string $tag
     * @return IdgClient
     * @throws IdgException
     */
    private function get(string $tag): IdgClient {
        if (! key_exists($tag, $this->clientMapping)) {
            $this->newClient($tag);
        }
        return $this->clientMapping[$tag];
    }

    /**
     * 加锁取 $tag 对应的单例 IdgClient 实例
     * @param string $tag
     * @return IdgClient
     * @throws IdgException
     */
    private function getLock(string $tag): IdgClient {
        if (! key_exists($tag, $this->clientMapping)) {
            $this->storage->lock($tag);
            $this->newClient($tag);
            $this->storage->unlock($tag);
        }
        return $this->clientMapping[$tag];
    }

    /**
     * 实例化客户端
     * @param string $tag
     * @return IdgClient
     * @throws IdgException
     */
    private function newClient(string $tag): IdgClient {
        $batch = $this->storage->load($tag);
        if (! $batch) {
            $batch = $this->register($tag);
            $this->storage->save($tag, $batch);
        }
        return $this->clientMapping[$tag] = (match($batch->type) {
            'increment' => new IdgClientIncrement($tag, $this->request),
            'time' => new IdgClientTime($tag, $this->request),
        })->setBatch($batch);
    }

    /**
     * 客户端ID标签注册
     * @param string $tag
     * @return IdgBatch
     * @throws IdgException
     */
    private function register(string $tag): IdgBatch {
        $batch = $this->request->register($tag);
        if (! $batch) {
            throw new IdgException("服务端未配置 {$tag} 标签");
        }
        $batch->batchId = $batch->batchFrom;
        $batch->batchApplyTime = time();
        return $batch;
    }
}