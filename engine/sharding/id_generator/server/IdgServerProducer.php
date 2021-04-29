<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-18 0:15
 */

namespace dce\sharding\id_generator\server;

use dce\sharding\id_generator\bridge\IdgBatch;
use dce\sharding\id_generator\IdgException;

trait IdgServerProducer {
    private static int $default_batch_count = 8192;

    /**
     * 增量ID池生成器
     * @param IdgBatch $batch
     */
    protected function produceIncrement(IdgBatch $batch): void {
        if (! isset($batch->batchCount)) {
            // 如果未配置单次批量ID数, 则默认为8192
            $batch->batchCount = self::$default_batch_count;
        }
        // 如果未初始化过, 则初始化新批起始值为1 (自增型ID从1开始递增), 否则为前次截至值+1
        $batch->batchFrom = ($batch->batchTo ?? 0) + 1;
        $batch->batchTo = $batch->batchFrom + $batch->batchCount - 1;
    }

    /**
     * TimeId池生成器
     * @param IdgBatch $batch
     * @throws IdgException
     */
    protected function produceTime(IdgBatch $batch): void {
        $time = time();
        if (! isset($batch->timeId)) {
            $batch->timeId = $time;
        }
        if (! isset($batch->batchBitWidth)) {
            throw new IdgException(IdgException::BATCH_BIT_WIDTH_MISSING);
        }
        if (! isset($batch->batchCount)) {
            $batch->batchCount = self::$default_batch_count;
        }
        $maxBatchId = (1 << $batch->batchBitWidth) - 1; // 计算最大批次ID
        if (! isset($batch->batchTo)) { // 如果未初始化过, 则初始化新批起始值为0
            $batch->batchFrom = 0;
        } else if ($time > $batch->timeId) { // 如果距前次生产已过1秒
            $batch->timeId = $time; // 记录新的时间戳
            $batch->batchFrom = 0; // 初始化新批起始值为0
        } else {
            $batch->batchFrom += $batch->batchCount; // 初始化新批起始值为0
            if ($batch->batchFrom > $maxBatchId) { // 如果ID起始值超出范围, 则表示同一秒内生产了过多的ID
                $microTime = microtime(1);
                $nextLess = 1000000 * (ceil($microTime) - $microTime) + 777; // 算出距下一秒的剩余微妙数
                usleep($nextLess); // 阻塞到下一秒继续生产 (您也可以将单秒ID池调大)
                $this->produceTime($batch);
                return;
            }
        }
        $batch->batchTo = $batch->batchFrom + $batch->batchCount - 1;
        if ($batch->batchTo > $maxBatchId) { // 如果截止ID超出范围, 则重定义为最大有效ID
            $batch->batchTo = $maxBatchId;
        }
    }
}
