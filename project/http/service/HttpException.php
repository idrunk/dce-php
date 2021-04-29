<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/26 4:01
 */

namespace http\service;

use dce\base\Exception;
use dce\i18n\Language;

// 630-649
class HttpException extends Exception {
    #[Language(['$rawRequestHttpClass属性值非RawRequestHttp族类'])]
    public const RAW_REQUEST_HTTP_CLASS_ERROR = 630;
}