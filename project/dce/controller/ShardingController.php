<?php
/**
 * Author: Drunk
 * Date: 2020-05-14 10:59
 */

namespace dce\controller;

use dce\service\extender\MysqlModuloExtender;
use dce\project\view\ViewCli;

class ShardingController extends ViewCli {
    public function extend() {
        MysqlModuloExtender::run($this);
    }
}
