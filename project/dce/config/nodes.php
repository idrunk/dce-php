<?php
/**
 * Author: Drunk
 * Date: 2020-05-14 10:57
 */

return [
    // 响应空路径cli请求
    [
        'methods' => 'cli',
        'path' => 'dce',
        'omissible_path' => true,
        'controller' => 'CliController->index',
    ],

    // 响应空路径长连接请求
    [
        'methods' => ['websocket', 'tcp', 'udp'],
        'path' => 'empty/connection',
        'controller' => 'ConnectionController->empty'
    ],

    // 响应空路径HTTP请求
    [
        'methods' => ['get', 'post', 'put', 'delete', 'options', 'head'],
        'path' => 'empty/http',
        'controller' => 'HttpController->empty'
    ],
    [
        'path' => 'empty/http/ajax',
        'controller' => 'HttpController->emptyAjax'
    ],

    // 分库拓库工具
    [
        'path' => 'sharding/extend',
        'controller' => 'ShardingController->extend',
        'enable_coroutine' => true,
    ],

    // 综合工具
    [
        'path' => 'cache/clear',
        'controller' => 'UtilityController->cacheClear',
    ],
];
