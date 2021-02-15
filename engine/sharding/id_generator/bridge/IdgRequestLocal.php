<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-19 1:48
 */

namespace dce\sharding\id_generator\bridge;

use dce\sharding\id_generator\IdgException;
use dce\sharding\id_generator\server\IdgServer;

class IdgRequestLocal implements IdgRequestInterface {
    private IdgServer $server;

    public function __construct(IdgServer $server) {
        $this->server = $server;
    }

    /**
     * 注意, 实际环境中您需要自己实现request接口, 集群环境中服务端应该部署在远程服务器上, 您应该基于RPC等方式实现接口来实现注册及生成ID池
     * @param string $tag
     * @return IdgBatch
     * @throws IdgException
     */
    public function register(string $tag): IdgBatch {
        return $this->server->register($tag);
    }

    /**
     * @param string $tag
     * @return IdgBatch
     * @throws IdgException
     */
    public function generate(string $tag): IdgBatch {
        return $this->server->generate($tag);
    }
}
