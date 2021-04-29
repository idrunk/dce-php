<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 10:24
 */

namespace dce\model\validator;

use dce\base\Exception;
use dce\i18n\Language;

// 1110-1199
class ValidatorException extends Exception {
    // 脚本异常
    #[Language(['校验器 %s 无效'])]
    public const VALIDATOR_INVALID = 1110;

    #[Language(['校验器 %s 未继承自分类校验器'])]
    public const VALIDATOR_MUST_BE_EXTENDS = 1111;

    #[Language(['%s 字段的校验器属性 %s 必须为数组'])]
    public const VALIDATOR_PROPERTY_MUST_BE_ARRAY = 1120;

    #[Language(['{{label}}校验器未配置formatSet或type属性'])]
    public const FORMATSET_OR_TYPE_REQUIRED = 1121;

    #[Language(['{{label}}校验器未配置regexp'])]
    public const REGEXP_REQUIRED = 1122;

    #[Language(['{{label}}校验器未配置set属性'])]
    public const SET_REQUIRED = 1123;

    #[Language(['{{label}}校验器未配置有效type属性'])]
    public const TYPE_REQUIRED = 1124;

    #[Language(['当前模型不支持唯一值校验'])]
    public const NOT_SUPPORT_UNIQUE = 1125;

    // 交互异常
    #[Language(['%s 无法通过 %s 校验'])]
    public const VALIDATE_FAILED = 1130;

    #[Language(['{{label}}格式不正确'])]
    public const FORMAT_INVALID = 1131;

    #[Language(['{{label}}不能迟于{{max}}'])]
    public const CANNOT_LATE_THAN = 1132;

    #[Language(['{{label}}不能早于{{min}}'])]
    public const CANNOT_EARLIER_THAN = 1133;

    #[Language(['{{label}}非有效Email地址'])]
    public const INVALID_EMAIL = 1134;

    #[Language(['{{label}}非有效IP地址'])]
    public const INVALID_IP = 1135;

    #[Language(['{{label}}非有效数字'])]
    public const INVALID_NUMBER = 1136;

    #[Language(['{{label}}不能为负数'])]
    public const CANNOT_BE_NEGATIVE = 1137;

    #[Language(['{{label}}不能为小数'])]
    public const CANNOT_BE_FLOAT = 1138;

    #[Language(['{{label}}不能小于{{min}}'])]
    public const CANNOT_SMALL_THAN = 1139;

    #[Language(['{{label}}不能大于{{max}}'])]
    public const CANNOT_LARGE_THAN = 1140;

    #[Language(['{{regexp}} 不是有效正则表达式'])]
    public const INVALID_REGEXP = 1141;

    #[Language(['{{label}}输入不正确'])]
    public const INVALID_INPUT = 1142;

    #[Language(['{{label}}值{{value}}必须为{{set}}中的一个'])]
    public const VALUE_NOT_IN_SET = 1143;

    #[Language(['{{label}}非有效字符'])]
    public const INVALID_STRING = 1144;

    #[Language(['{{label}}不能小于{{min}}个字符'])]
    public const CANNOT_LESS_THAN = 1145;

    #[Language(['{{label}}不能多于{{max}}个字符'])]
    public const CANNOT_MORE_THAN = 1146;

    #[Language(['{{label}}非有效Url地址'])]
    public const INVALID_URL = 1147;

    #[Language(['缺少必传参数{{label}}'])]
    public const REQUIRED_MISSING = 1148;

    #[Language(['{{label}}不能为空'])]
    public const CANNOT_BE_EMPTY = 1149;

    #[Language(['{{label}}不能重复'])]
    public const CANNOT_REPEAT = 1150;
}
