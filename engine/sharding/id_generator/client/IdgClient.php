<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-16 15:40
 */

namespace dce\sharding\id_generator\client;

use dce\sharding\id_generator\bridge\IdgBatch;
use dce\sharding\id_generator\bridge\IdgRequestInterface;
use dce\sharding\id_generator\IdgException;

/**
 * Drunk ID生成器客户端
 */
abstract class IdgClient {
    /**
     * 最大错误申请重试数
     */
    private const MAX_ERROR_APPLY_COUNT = 5;

    /** @var int 当前申请连续出错数 */
    private int $applyErrorCount = 0;

    /** @var bool 批次是否已过期 */
    private bool $isExpired = false;

    /** @var IdgBatch 客户端缓存批次 (缓存未用完前不访问实体) */
    private IdgBatch $cacheBatch;

    /** @var int 批次池有效时长 */
    private int $batchValidDuration = 60;

    /** @var int 缓存批次单批容量 */
    private int $cacheBatchCount = 64;

    /** @var IdgBatch 客户端批次储存实体 */
    protected IdgBatch $batch;

    /**
     * IdgClient constructor.
     * @param string $tag
     * @param IdgRequestInterface $request
     */
    public function __construct(
        private string $tag,
        private IdgRequestInterface $request
    ) {}

    /**
     * 设置批次配置
     * @param IdgBatch $batch
     * @return $this
     */
    public function setBatch(IdgBatch $batch): self {
        $this->batch = $batch;
        return $this;
    }

    /**
     * 取配置
     * @return IdgBatch
     */
    public function getBatch(): IdgBatch {
        return $this->batch;
    }

    /**
     * 生成ID
     * @param int|string $uid
     * @return int
     */
    final public function generate(int|string $uid = 0): int {
        $base = $nextBit = 0;
        [$base, $nextBit] = $this->generateServer($base, $nextBit);
        [$base, $nextBit] = $this->generateModulo($base, $nextBit, $uid);
        $base = static::generateBatch($this->cacheBatch, $base, $nextBit);
        return $base;
    }

    /**
     * 校验缓存批次有效性 (是否初始化, 是否已过期等)
     * @return bool
     */
    final public function validCacheBatch(): bool {
        if (! isset($this->cacheBatch)) {
            return false;
        }
        return $this->validBatch($this->cacheBatch);
    }

    /**
     * 申请新的缓存批次
     * @throws IdgException
     */
    final public function applyCacheBatch(): void {
        $batchId = $this->batch->batchId;
        $this->batch->batchId += $this->cacheBatchCount;
        // 如果截止缓存批次ID溢出, 则需申请新批次
        if (! $this->validBatch($this->batch)) {
            $this->applyBatch();
            $batchId = $this->batch->batchId;
            $this->batch->batchId += $this->cacheBatchCount;
        }
        $this->cacheBatch = IdgBatch::new()->setProperties($this->batch->arrayify());
        $this->cacheBatch->batchCount = $this->cacheBatchCount;
        $this->cacheBatch->batchId = $batchId;
        $this->cacheBatch->batchFrom = $batchId;
        $this->cacheBatch->batchTo = $batchId + $this->cacheBatchCount - 1;
    }

    /**
     * 批量生成ID
     * @param int $count
     * @param int|string $uid 用户ID, 若为字符串, 则会自动转为crc32的int
     * @return array
     * @throws IdgException
     */
    final public function batchGenerate(int $count, int|string $uid = 0): array {
        $ids = [];
        for ($i = 0; $i < $count; $i ++) {
            if (! $this->validBatch($this->batch)) {
                // 如果目标 batchId 无效, 则申请新批次
                $this->applyBatch();
            }
            $base = $nextBit = 0;
            [$base, $nextBit] = $this->generateServer($base, $nextBit);
            [$base, $nextBit] = $this->generateModulo($base, $nextBit, $uid);
            $base = static::generateBatch($this->batch, $base, $nextBit);
            $ids[] = $base;
        }
        return $ids;
    }

    /**
     * 批次ID种子池失效拉新
     * @param IdgBatch $batch
     * @return bool
     */
    private function validBatch(IdgBatch $batch): bool {
        $this->isExpired = time() - $batch->batchApplyTime > $this->batchValidDuration;
        // 是否ID种子未用完且未过期
        return $batch->batchId <= $batch->batchTo && ! $this->isExpired;
    }

    /**
     * 从服务端申请批次ID种子池
     * @return bool
     * @throws IdgException
     */
    private function applyBatch(): bool {
        $newBatch = $this->request->generate($this->tag);
        if (! $newBatch) {
            $this->applyErrorCount ++;
            if ($this->applyErrorCount > self::MAX_ERROR_APPLY_COUNT) {
                // 如果超出申请失败次数, 则抛出异常
                throw new IdgException(IdgException::BASE_ID_GENERATE_FAILED);
            }
            return $this->applyBatch();
        }
        $this->checkBatchIntegrity($newBatch);
        $this->applyErrorCount = 0;
        $this->batch->batchId = $newBatch->batchFrom;
        if ($this->isExpired) {
            // 如果是因为过期申请的新池, 则随机偏移一下, 以解决按模分库模式时, 由于不活跃导致每次生成的ID都映射到第一个分库的问题
            $this->batch->batchId += rand(0, $this->batch->batchCount / 16);
            $this->isExpired = false;
        }
        // 保存本批生成时间, 以供后续作过期处理 (客户端维护过期, 无需与服务端校对时间)
        $this->batch->batchApplyTime = time();
        $this->batch->setProperties($newBatch->arrayify());
        return true;
    }

    /**
     * 批次数据完整性校验
     * @param IdgBatch $batch
     * @throws IdgException
     */
    protected static function checkBatchIntegrity(IdgBatch $batch): void {
        if (! isset($batch->batchFrom)) {
            throw new IdgException(IdgException::APPLIED_BATCH_FROM_INVALID);
        }
        if (! isset($batch->batchTo)) {
            throw new IdgException(IdgException::APPLIED_BATCH_TO_INVALID);
        }
    }

    /**
     * 生成服务ID部分
     * @param int $base
     * @param int $nextBit
     * @return array
     */
    private function generateServer(int $base, int $nextBit): array {
        if (! empty($this->batch->serverBitWidth)) {
            $base += $this->batch->serverId;
            $nextBit += $this->batch->serverBitWidth;
        }
        return [$base, $nextBit];
    }

    /**
     * 生成模数部分
     * @param int $base
     * @param int $nextBit
     * @param int|string $uid
     * @return array
     */
    private function generateModulo(int $base, int $nextBit, int|string $uid): array {
        // 如果未配置, 则无需uid段
        if (! empty($this->batch->moduloBitWidth)) {
            if (! is_numeric($uid)) {
                // 如果UID为字符串, 则转为无符号crc32整数
                $uid = sprintf('%u', crc32($uid));
            }
            $moduloId = $uid % (1 << $this->batch->moduloBitWidth);
            $base += $moduloId << $nextBit;
            $nextBit += $this->batch->moduloBitWidth;
        }
        return [$base, $nextBit];
    }

    /**
     * 生成批次ID部分
     * @param IdgBatch $batch
     * @param int $base
     * @param int $nextBit
     * @return int
     */
    protected static function generateBatch(IdgBatch $batch, int $base, int $nextBit): int {
        $base += $batch->batchId << $nextBit;
        $batch->batchId ++;
        return $base;
    }

    /**
     * 根据ID提取分库基因
     * @param int $id
     * @param int $modulus 模数, 若传入了则执行取模运算计算余数
     * @return int
     */
    public function extractGene(int $id, int $modulus = 0): int {
        if (! empty($this->batch->serverBitWidth)) {
            $id >>= $this->batch->serverBitWidth;
        }
        if ($modulus) {
            $id %= $modulus;
        }
        return $id;
    }

    /**
     * 分解ID (从ID取组成部分,[server_id, modulo_id, batch_id, time_id])
     * @param int $id
     * @return IdgBatch
     */
    public function parse(int $id): IdgBatch {
        $batch = IdgBatch::new();
        if (! empty($this->batch->serverBitWidth)) {
            $batch->serverId = self::parsePart($id, $this->batch->serverBitWidth);
        }
        if (! empty($this->batch->moduloBitWidth)) {
            $batch->moduloId = self::parsePart($id, $this->batch->moduloBitWidth);
        }
        if ($id) {
            $batch->batchId = $id;
        }
        return $batch;
    }

    /**
     * 分解组件
     * @param int $id
     * @param int $bitWidth
     * @return int
     */
    protected static function parsePart(int &$id, int $bitWidth): int {
        if (! $bitWidth) {
            return 0;
        }
        $part = $id & ((1 << $bitWidth) - 1);
        $id >>= $bitWidth;
        return $part;
    }
}
