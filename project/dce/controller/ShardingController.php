<?php
/**
 * Author: Drunk
 * Date: 2020-05-14 10:59
 */

namespace dce\controller;

use dce\project\Controller;
use dce\project\node\Node;
use dce\service\extender\MysqlModuloExtender;

class ShardingController extends Controller {
    #[Node('sharding/extend', 'cli', enableCoroutine: true)]
    public function extend() {
        MysqlModuloExtender::run($this);
    }
}
