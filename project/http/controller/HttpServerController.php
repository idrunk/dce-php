<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/16 2:06
 */

namespace http\controller;

use dce\config\ConfigException;
use dce\project\request\Request;
use dce\project\view\ViewCli;
use http\service\HttpServer;
use rpc\http\service\HttpServerApi;

class HttpServerController extends ViewCli {
    private HttpServer $server;

    public function __construct(Request $request) {
        parent::__construct($request);
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
        HttpServerApi::stop();
        $this->print('Http server was stopped.');
    }

    public function reload() {
        HttpServerApi::reload();
        $this->print('Http server was reloaded.');
    }

    public function status() {
        $status = HttpServerApi::status();
        $status = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $this->print($status);
    }
}
