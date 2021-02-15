<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-11 01:36
 */

namespace dce\controller;

use dce\project\view\ViewCli;

class CliController extends ViewCli {
    public function index() {
        $this->print("\n你正在cli模式以空路径请求Dce接口");
    }
}