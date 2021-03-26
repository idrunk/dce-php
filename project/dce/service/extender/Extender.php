<?php
/**
 * Author: Drunk
 * Date: 2020-05-14 18:16
 */

namespace dce\service\extender;

use dce\project\Controller;
use dce\sharding\middleware\ShardingConfig;

abstract class Extender {
    protected string $shardingType;

    protected string $dbType;

    /** @var ShardingConfig[] */
    protected array $shardingConfigs = [];

    protected array $srcDatabases;

    protected array $extendConfig;

    protected array $extendDatabases;

    protected array $extendMappings;

    protected array $connections;

    protected Controller $cli;

    protected function input(string $value = ''): string {
        $value = date('[H:i:s] ') . $value;
        return $this->cli->input($value);
    }

    protected function print(string $value, string $suffix = "\n"): void {
        $value = date('[H:i:s] ') . $value;
        $this->cli->print($value, $suffix);
    }
}
