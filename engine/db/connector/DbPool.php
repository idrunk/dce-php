<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/7/11 11:36
 */

namespace dce\db\connector;

use dce\pool\PoolProductionConfig;
use dce\pool\Pool;

class DbPool extends Pool {
    protected function produce(PoolProductionConfig $config): DbConnector {
        $connector = new PdoDbConnector();
        $connector->connect($config->dbName, $config->host, $config->dbUser, $config->dbPassword, $config->dbPort, false);
        return $connector;
    }

    public function fetch(): DbConnector {
        return $this->get();
    }

    public static function inst(string ... $identities): static {
        return parent::getInstance(DbPoolProductionConfig::class, ... $identities);
    }
}
