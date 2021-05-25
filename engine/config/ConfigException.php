<?php
/**
 * Author: Drunk
 * Date: 2020-04-27 15:49
 */

namespace  dce\config;

use dce\base\Exception;
use dce\i18n\Language;

// 1120-1149
class ConfigException extends Exception {
    #[Language(['配置迭代赋值出错'])]
    public const ITERATION_ASSIGNMENT_ERROR = 1120;

    #[Language(['配置 %s 非法'])]
    public const CONFIG_ITEM_ERROR = 1121;

    #[Language('数据库配置 %s 为空')]
    public const DB_CONFIG_EMPTY = 1130;

    #[Language('http.service配置非有效HttpServer类')]
    public const HTTP_SERVICE_INVALID = 1140;

    #[Language('websocket.service配置非有效WebsocketServer类')]
    public const WEBSOCKET_SERVICE_INVALID = 1141;

    #[Language('tcp.service配置非有效TcpServer类')]
    public const TCP_SERVICE_INVALID = 1142;
}
