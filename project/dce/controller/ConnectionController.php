<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-11 03:06
 */

namespace dce\controller;

use dce\service\server\ViewConnection;

class ConnectionController extends ViewConnection {
    public function empty() {
        $this->assign('info', '恭喜！服务端收到了你的消息并给你作出了回应');
        $this->response();
    }
}