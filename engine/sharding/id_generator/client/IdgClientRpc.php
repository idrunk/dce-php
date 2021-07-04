<?php
/**
 * Author: Drunk
 * Date: 2019/10/15 19:21
 */

namespace rpc\didg;

use dce\Dce;
use dce\rpc\RpcMatrix;
use dce\sharding\id_generator\IdGenerator;

class IdgClientRpc extends RpcMatrix {
    public static function generate(string $tag, int|string $uid = 0, string|null $geneTag = null): int {
        return Dce::$config->idGenerator->newClient($tag)->generate($tag, $uid, $geneTag);
    }

    public static function batchGenerate(string $tag, int $count, int|string $uid = 0, string|null $geneTag = null): array {
        return Dce::$config->idGenerator->newClient($tag)->batchGenerate($tag, $count, $uid, $geneTag);
    }

    public static function getClient(string $tag): IdGenerator {
        return Dce::$config->idGenerator->newClient($tag);
    }
}
