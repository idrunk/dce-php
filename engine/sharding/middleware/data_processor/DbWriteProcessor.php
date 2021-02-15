<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 15:13
 */

namespace dce\sharding\middleware\data_processor;

class DbWriteProcessor extends DataProcessor {
    public function queryGetInsertId(): int|string {
        return $this->sourceData[0] ?? 0;
    }

    public function queryGetAffectedCount(): int {
        return array_sum($this->sourceData);
    }
}
