<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/26 4:01
 */

namespace websocket\service;

use dce\base\Exception;
use dce\i18n\Language;

// 1050-1069
class WebsocketException extends Exception {
    #[Language(['$rawRequestWebsocketClass属性值非RawRequestConnection族类'])]
    public const RAW_REQUEST_WEBSOCKET_CLASS_ERROR = 1050;
}