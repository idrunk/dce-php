<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/1 16:46
 */

namespace dce\model;

use dce\base\Exception;
use dce\i18n\Language;

// 1100-1109
class ModelException extends Exception {
    #[Language(['未定义属性 %s 对应的getter方法'])]
    public const GETTER_UNDEFINED = 1100;
}