<?php
/**
 * Author: Drunk
 * Date: 2020-05-14 10:59
 */

namespace dce\controller;

use dce\project\Controller;
use dce\service\extender\MysqlModuloExtender;

class ShardingController extends Controller {
    public function extend() {
        MysqlModuloExtender::run($this);
    }
}
