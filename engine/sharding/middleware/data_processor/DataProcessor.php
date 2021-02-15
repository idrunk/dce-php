<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/10/7 12:55
 */

namespace dce\sharding\middleware\data_processor;

abstract class DataProcessor {
    protected array $sourceData = [];

    public function merge(array|int|string $resultData): void {
        $this->sourceData[] = $resultData;
    }
}
