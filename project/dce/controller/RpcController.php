<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-10-31 10:01
 */

namespace dce\controller;

use dce\event\Daemon;
use dce\project\node\Node;

class RpcController extends DceController {
    #[Node('rpc', controllerPath: true)]
    public function __init(): void {
        parent::__init();
    }

    #[Node]
    public function start(): void {
        Daemon::tryRunService(Daemon::ServiceRpc);
    }
}