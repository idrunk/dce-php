<?php
namespace rpc\didg;

use dce\Dce;
use dce\rpc\RpcMatrix;
use dce\sharding\id_generator\bridge\IdgBatch;

class IdgServerRpc extends RpcMatrix {
    public static function register(string $tag): IdgBatch {
        return Dce::$config->idGenerator->newServer()->register($tag);
    }

    public static function generate(string $tag): IdgBatch {
        return Dce::$config->idGenerator->newServer()->generate($tag);
    }
}