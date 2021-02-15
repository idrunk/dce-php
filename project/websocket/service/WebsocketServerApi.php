<?php
/**
 * Author: Drunk
 * Date: 2020-04-21 19:24
 */

namespace rpc\websocket\service;

use dce\service\server\ServerApi;

class WebsocketServerApi extends ServerApi {
    /**
     * 向Websocket客户端推送消息
     * @param int $fd
     * @param mixed $value
     * @param string $path
     * @return bool
     */
    public static function push(int $fd, mixed $value, string $path): bool {
        return self::$server->push($fd, $value, $path);
    }
}
