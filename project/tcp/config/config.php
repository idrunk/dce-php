<?php
/**
 * Author: Drunk
 * Date: 2020-04-28 19:37
 */

return [
    'tcp' => [
        'host' => '0.0.0.0',
        'port' => 20462,
        'mode' => SWOOLE_PROCESS,
        'sock_type' => SWOOLE_SOCK_TCP,
        'service' => '\\tcp\\service\\TcpServer',
        'extra_ports' => [
            ['host' => '0.0.0.0', 'port' => 20463, 'sock_type' => SWOOLE_SOCK_UDP], // 同时监听20463端口的Udp服务
        ],
    ],
    '#extends' => [ // 扩展用户自定义的配置
        APP_COMMON . 'config/tcp.php',
    ],
];
