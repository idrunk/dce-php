<?php
/**
 * Author: Drunk
 * Date: 2020-04-28 19:38
 */

return [
    [
        'methods' => 'cli',
        'path' => 'tcp',
    ],
    [
        'path' => 'start',
        'name' => '启动服务',
        'controller' => 'TcpServerController->start',
    ],
    [
        'path' => 'stop',
        'name' => '停止服务',
        'controller' => 'TcpServerController->stop',
        'enable_coroutine' => true,
    ],
    [
        'path' => 'reload',
        'name' => '重启服务',
        'controller' => 'TcpServerController->reload',
        'enable_coroutine' => true,
    ],
    [
        'path' => 'status',
        'name' => '状态信息',
        'controller' => 'TcpServerController->status',
        'enable_coroutine' => true,
    ],
];
