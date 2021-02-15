<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 1:53
 */

return [
    [
        'methods' => 'cli',
        'path' => 'http',
    ],
    [
        'path' => 'start',
        'name' => '启动服务',
        'controller' => 'HttpServerController->start',
    ],
    [
        'path' => 'stop',
        'name' => '停止服务',
        'controller' => 'HttpServerController->stop',
        'enable_coroutine' => true,
    ],
    [
        'path' => 'reload',
        'name' => '重启服务',
        'controller' => 'HttpServerController->reload',
        'enable_coroutine' => true,
    ],
    [
        'path' => 'status',
        'name' => '状态信息',
        'controller' => 'HttpServerController->status',
        'enable_coroutine' => true,
    ],
];
