<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/25 3:29
 */

namespace websocket\controller;

use dce\config\ConfigException;
use dce\project\Controller;
use dce\project\request\Request;
use rpc\dce\service\RpcServerApi;
use websocket\service\WebsocketServer;

class WebsocketServerController extends Controller {
    private WebsocketServer $server;

    public function __init(): void {
        $serverClass = $this->request->config->websocket['service'];
        if (! is_a($serverClass, WebsocketServer::class, true)) {
            throw new ConfigException(ConfigException::WEBSOCKET_SERVICE_INVALID);
        }
        // 构造函数内会挂载RPC客户端, 所以整个公共的呗
        $this->server = new $serverClass();
    }

    public function start() {
        $this->server->start($this->request->pureCli);
    }

    public function stop() {
        RpcServerApi::stop();
        $this->print('Websocket server was stopped.');
    }

    public function reload() {
        RpcServerApi::reload();
        $this->print('Websocket server was reloaded.');
    }

    public function status() {
        $status = RpcServerApi::status();
        $status = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->print($status);
    }
}
