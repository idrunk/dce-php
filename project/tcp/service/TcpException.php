<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/28 22:31
 */

namespace tcp\service;

use dce\base\Exception;
use dce\i18n\Language;

// 1010-1029
class TcpException extends Exception {
    #[Language(['$rawRequestTcpClass属性值非RawRequestConnection族类'])]
    public const RAW_REQUEST_TCP_CLASS_ERROR = 1010;
}