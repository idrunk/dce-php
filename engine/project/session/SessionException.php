<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/2/23 18:09
 */

namespace dce\project\session;

use dce\base\Exception;
use dce\i18n\Language;

// 1260-1279
class SessionException extends Exception {
    #[Language(['Session实例仅能绑定一个sid'])]
    public const SID_CAN_BIND_ONCE = 1260;

    #[Language(['Fdid %s 异常, 无法找到对应的sid'])]
    public const SID_BY_FDID_NOTFOUND = 1270;

    #[Language(['未指定要绑定给SessionForm %s 的sid或fdids'])]
    public const EMPTY_FORM_PARAMETERS = 1271;
}