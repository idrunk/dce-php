<?php
/**
 * Author: Drunk
 * Date: 2020-04-16 18:25
 */

return [
    'http' => [
        'host' => '0.0.0.0',
        'port' => 20460,
        'service' => '\\http\\service\\HttpServer',
    ],
    'swoole_http' => [
        'enable_static_handler' => true,
        'document_root' => dce\Dce::$config->wwwPath,
    ],
    '#extends' => [
        APP_COMMON . 'config/http.php',
    ],
];
