<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/16 2:06
 */

namespace http\controller;

use dce\config\ConfigException;
use dce\project\Controller;
use dce\project\request\Request;
use http\service\HttpServer;
use rpc\dce\service\RpcServerApi;

class HttpServerController extends Controller {
    private HttpServer $server;

    public function __init(): void {
        $serverClass = $this->request->config->http['service'] ?? '';
        if (! is_a($serverClass, HttpServer::class, true)) {
            throw new ConfigException('http.service配置非有效WebsocketService类');
        }
        // 在这里初始化是因为需要准备RpcClient
        $this->server = new $serverClass();
    }

    public function start() {
        $this->server->start($this->request->pureCli);
    }

    public function stop() {
        RpcServerApi::stop();
        $this->print('Http server was stopped.');
    }

    public function reload() {
        RpcServerApi::reload();
        $this->print('Http server was reloaded.');
    }

    public function status() {
        $status = RpcServerApi::status();
        $status = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->print($status);
    }
}
