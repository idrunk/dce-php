<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 15:13
 */

namespace dce\sharding\middleware\data_processor;

use dce\sharding\middleware\MiddlewareException;

class DbWriteProcessor extends DataProcessor {
    public function queryGetInsertId(): int|string {
        ! isset($this->sourceData[0]) && throw new MiddlewareException(MiddlewareException::INSERT_FAILED_NO_ID);
        return $this->sourceData[0];
    }

    public function queryGetAffectedCount(): int {
        return array_sum($this->sourceData);
    }
}
