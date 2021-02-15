<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 1:53
 */

return [
    [
        'methods' => 'cli',
        'path' => 'websocket',
    ],
    [
        'path' => 'start',
        'name' => '启动服务',
        'controller' => 'WebsocketServerController->start',
    ],
    [
        'path' => 'stop',
        'name' => '停止服务',
        'controller' => 'WebsocketServerController->stop',
        'enable_coroutine' => true,
    ],
    [
        'path' => 'reload',
        'name' => '重启服务',
        'controller' => 'WebsocketServerController->reload',
        'enable_coroutine' => true,
    ],
    [
        'path' => 'status',
        'name' => '状态信息',
        'controller' => 'WebsocketServerController->status',
        'enable_coroutine' => true,
    ],
];
