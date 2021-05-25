<?php
/**
 * Author: Drunk
 * Date: 2021-4-21 1:28
 */

namespace dce\cache;

use dce\base\Exception;
use dce\i18n\Language;

// 1150-1169
class CacheException extends Exception {
    #[Language(['%s 不支持 exists 方法', '%s not support exists method'])]
    public const NOT_SUPPORT_EXISTS = 1150;
}