<?php
/**
 * Author: Drunk (idrunk.net drunkce.com)
 * Date: 2020-12-15 15:49
 */

namespace dce\sharding\id_generator\bridge;

use rpc\didg\IdgServerRpc;

class IdgRequestRpc implements IdgRequestInterface {
    function register(string $tag): IdgBatch {
        return IdgServerRpc::register($tag);
    }

    function generate(string $tag): IdgBatch {
        return IdgServerRpc::generate($tag);
    }
}