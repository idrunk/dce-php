<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/25 3:29
 */

namespace websocket\controller;

use dce\config\ConfigException;
use dce\project\request\Request;
use dce\project\view\ViewCli;
use rpc\websocket\service\WebsocketServerApi;
use websocket\service\WebsocketServer;

class WebsocketServerController extends ViewCli {
    private WebsocketServer $server;

    public function __construct(Request $request) {
        parent::__construct($request);
        $serverClass = $this->request->config->websocket['service'];
        if (! is_a($serverClass, WebsocketServer::class, true)) {
            throw new ConfigException('websocket.service配置非有效WebsocketService类');
        }
        // 构造函数内会挂载RPC客户端, 所以整个公共的呗
        $this->server = new $serverClass();
    }

    public function start() {
        $this->server->start($this->request->pureCli);
    }

    public function stop() {
        WebsocketServerApi::stop();
        $this->print('Websocket server was stopped.');
    }

    public function reload() {
        WebsocketServerApi::reload();
        $this->print('Websocket server was reloaded.');
    }

    public function status() {
        $status = WebsocketServerApi::status();
        $status = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->print($status);
    }
}
