<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 14:34
 */

namespace dce\db\entity;

use dce\base\Exception;
use dce\i18n\Language;

// 1440-1479
class SchemaException extends Exception {
    #[Language(['暂不支持 %s 类型字段'])]
    public const FIELD_TYPE_NOT_SUPPORT = 1440;
}
