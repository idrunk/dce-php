<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-16 15:29
 */

namespace dce\sharding\id_generator\client;

use dce\sharding\id_generator\bridge\IdgBatch;
use dce\sharding\id_generator\IdgException;

/**
 * Class Time
 * @method generate() 生成时间因素id, 最终结构, {timeId}{batchId}{uidHash}{serviceId}, 除开serviceId皆可为hash因素
 * (建议{timeId}{batchId:20bit}{serviceId:8bit}, 该方案bigint范围timeId可持续1000年, batchId单个服务一秒可生成1048575个ID, 可部署256个ID生成服务)
 * @package dce\sharding\id_generator\client
 */
class IdgClientTime extends IdgClient {
    /** @inheritDoc */
    protected static function checkBatchIntegrity(IdgBatch $batch): void {
        parent::checkBatchIntegrity($batch);
        if (empty($batch->timeId)) {
            throw new IdgException(IdgException::BASE_TIME_ID_MISSING);
        }
    }

    /**
     * 生成TimeId
     * @param IdgBatch $batch
     * @param int $base
     * @param int $nextBit
     * @return int
     */
    protected static function generateBatch(IdgBatch $batch, int $base, int $nextBit): int {
        $base = parent::generateBatch($batch, $base, $nextBit);
        // uid左移service比特位 (非bc库还是够用100年的吧)
        $base += $batch->timeId << ($nextBit + $batch->batchBitWidth);
        return $base;
    }

    /**
     * 解析TimeId
     * @param int $id
     * @return IdgBatch
     */
    public function parse(int $id): IdgBatch {
        $batch = parent::parse($id);
        $unParsedId = $batch->batchId;
        if (! empty($this->batch->batchBitWidth)) {
            $batch->batchId = self::parsePart($unParsedId, $this->batch->batchBitWidth);
        }
        // TimeId为解析后剩余部分
        $batch->timeId = $unParsedId;
        return $batch;
    }
}
