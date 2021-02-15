<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/5/4 0:47
 */

namespace dce\service\server;

use dce\rpc\RpcMatrix;

class ServerApi extends RpcMatrix {
    protected static ServerMatrix $server;

    /**
     * 记录服务对象
     * @param ServerMatrix $server
     */
    public static function logServer(ServerMatrix $server) {
        if (! isset(static::$server)) {
            static::$server = $server;
        }
    }

    /**
     * 停止服务
     */
    public static function stop(): void {
        static::$server->stop();
    }

    /**
     * 重载服务
     */
    public static function reload(): void {
        static::$server->reload();
    }

    /**
     * 获取服务状态
     * @return array
     */
    public static function status(): array {
        return static::$server->status();
    }

    /**
     * 向Tcp客户端推送消息
     * @param int $fd
     * @param mixed $value
     * @param string $path
     * @return bool
     */
    public static function send(int $fd, mixed $value, string $path): bool {
        return static::$server->send($fd, $value, $path);
    }

    /**
     * 向Udp客户端推送消息
     * @param string $host
     * @param int $port
     * @param mixed $value
     * @param string $path
     * @return bool
     */
    public static function sendTo(string $host, int $port, mixed $value, string $path): bool {
        return static::$server->sendTo($host, $port, $value, $path);
    }
}