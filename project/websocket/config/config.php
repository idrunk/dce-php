<?php
/**
 * Author: Drunk
 * Date: 2020-04-16 18:25
 */

return [
    'websocket' => [
        'host' => '0.0.0.0',
        'port' => 20461,
        'service' => '\\websocket\\service\\WebsocketServer',
    ],
    'swoole_websocket' => [
        'enable_static_handler' => true,
        'document_root' => APP_WWW,
    ],
    '#extends' => [ // 扩展用户自定义的配置
        APP_COMMON . 'config/websocket.php',
    ]
];
