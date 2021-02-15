<?php
/**
 * Author: Drunk
 * Date: 2017-1-26 16:55
 */

namespace drunk;

final class Regular {
    public const REG_ENG = [
        'label' => '英文',
        'unmatch' => '只能包含半角字母及下划线',
        'eg' => 'Michael_Jackson',
        'regexp' => '/^[a-z_A-Z]+$/',
    ], REG_ENG_NUM = [
        'label' => '英文数字',
        'unmatch' => '只能包含半角字母下划线及数字',
        'eg' => 'Michael_Jackson_01',
        'regexp' => '/^\w+$/',
    ], REG_ZH = [
        'label' => '中文',
        'unmatch' => '仅支持中文字符',
        'eg' => '中国',
        'regexp' => '/^[\x{4e00}-\x{9fa5}]+$/u',
        'regexp_js' => '/^[\u4e00-\u9fa5]+$/',
    ], REG_ENG_NUM_ZH = [
        'label' => '中文英文数字',
        'unmatch' => '仅支持中文英文数字及下划线',
        'eg' => '中文english123_',
        'regexp' => '^[\x{4e00}-\x{9fa5}\w]+$/u',
        'regexp_js' => '/^[\u4e00-\u9fa5\w]+$/',
    ], REG_INT = [
        'label' => '整数',
        'unmatch' => '仅能为正负整数',
        'eg' => '-123',
        'regexp' => '/^[+-]?\d+$/',
    ], REG_NUM = [
        'label' => '数字',
        'unmatch' => '仅能为正负数字包括小数',
        'eg' => '-123.123',
        'regexp' => '/^[+-]?\d+(\.\d+)?$/',
    ], REG_QQ = [
        'label' => 'QQ号码',
        'unmatch' => '正确格式为5至10位或12位的半角数字',
        'eg' => '10000',
        'regexp' => '/^[1-9](?:\d{4,9}|\d{11})$/',
    ], REG_MOBILE = [
        'label' => '手机号码',
        'unmatch' => '正确格式为11位的半角数字',
        'eg' => '18611112222',
        'regexp' => '/^1[34578]\d{9}$/',
    ], REG_TEL = [
        'label' => '电话号码',
        'unmatch' => '请输入区号及号码',
        'eg' => '0101111111或010 1111 111或010-1111-111',
        'regexp' => '/^(\d{3,4})?([ -]?\d{3,4}){2,3}$/',
    ], REG_POSTCODE = [
        'label' => '邮编',
        'unmatch' => '正确格式为6位的半角数字',
        'eg' => '100000',
        'regexp' => '/^[1-9]\d{5}$/',
    ], REG_DATETIME = [
        'label' => '日期时间',
        'unmatch' => '仅支持ISO日期时间格式',
        'eg' => '2012-12-22 22:22:22',
        'regexp' => '/^|[12]\d{3}-(1[012]|0?[1-9])-(0?[1-9]|[12]\d|3[01])\s+([01]?\d|2[0-3]):([0-5]?\d)(?::([0-5]?\d))?$/',
    ], REG_TIME = [
        'label' => '时间',
        'unmatch' => '仅支持ISO时间格式',
        'eg' => '22:22:22',
        'regexp' => '/^([01]?\d|2[0-3]):([0-5]?\d):([0-5]?\d)$/',
    ], REG_IDNUMBER = [
        'label' => '身份证号码',
        'unmatch' => '正确格式为15至18位的半角数字或17位数字加X的组合',
        'eg' => '110101199912221110',
        'regexp' => '/^((1[1-5])|(2[1-3])|(3[1-7])|(4[1-6])|(5[0-4])|(6[1-5])|71|(8[12])|91)\d{4}((19|20)\d{2}(1[012]|0?[1-9])(0?[1-9]|[12]\d|3[01])\d{3}[\dxX]|\d{2}(1[012]|0?[1-9])(0?[1-9]|[12]\d|3[01])\d{3})$/',
    ], REG_EMAIL = [
        'label' => '邮箱',
        'unmatch' => '只能包含半角字母数字下划线中文及小数点',
        'eg' => 'account@domain.com',
        'regexp' => '/^[\w\x{4e00}-\x{9fa5}]+(\.[\w\x{4e00}-\x{9fa5}]+)*@(([a-zA-Z0-9\x{4e00}-\x{9fa5}]+|[a-zA-Z0-9\x{4e00}-\x{9fa5}][-a-zA-Z0-9\x{4e00}-\x{9fa5}]*[a-zA-Z0-9\x{4e00}-\x{9fa5}])\.)+[a-zA-Z\x{4e00}-\x{9fa5}]+$/u',
        'regexp_js' => '/^[\w\u4e00-\u9fa5]+(\.[\w\u4e00-\u9fa5]+)*@(([a-zA-Z0-9\u4e00-\u9fa5]+|[a-zA-Z0-9\u4e00-\u9fa5][-a-zA-Z0-9\u4e00-\u9fa5]*[a-zA-Z0-9\u4e00-\u9fa5])\.)+[a-zA-Z\u4e00-\u9fa5]+$/',
    ], REG_URL = [
        'label' => '网址',
        'unmatch' => '正确格式以http开头的域名地址组合',
        'eg' => 'http://www.domain.com/?keyword=1',
        'regexp' => '/^https?:\/\/(([a-zA-Z0-9\x{4e00}-\x{9fa5}]+|[a-zA-Z0-9\x{4e00}-\x{9fa5}][-a-zA-Z0-9\x{4e00}-\x{9fa5}]*[a-zA-Z0-9\x{4e00}-\x{9fa5}])\.)*[a-zA-Z\x{4e00}-\x{9fa5}]+(?::\d+)?(?:\/.*?)?$/u',
        'regexp_js' => '/^https?:\/\/(([a-zA-Z0-9\u4e00-\u9fa5]+|[a-zA-Z0-9\u4e00-\u9fa5][-a-zA-Z0-9\u4e00-\u9fa5]*[a-zA-Z0-9\u4e00-\u9fa5])\.)*[a-zA-Z\u4e00-\u9fa5]+(:\d+)?(?:\/.*?)?$/',
    ], REG_IP4 = [
        'label' => 'IP4地址',
        'unmatch' => '正确格式为数字与小数点组合',
        'eg' => '255.255.255.255',
        'regexp' => '/^(\d|\d\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|\d\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|\d\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|\d\d|1\d\d|2[0-4]\d|25[0-5])$/',
    ], REG_ACCOUNT_ZH = [
        'label' => '中文帐号',
        'unmatch' => '正确格式为非数字或下划线开头,非下划线结尾的数字字母中文及下划线组合',
        'eg' => 'love_77',
        'regexp' => '/^([\x{4e00}-\x{9fa5}a-zA-Z]+|[\x{4e00}-\x{9fa5}a-zA-Z]+([\x{4e00}-\x{9fa5}\w]*?)[\x{4e00}-\x{9fa5}a-zA-Z0-9])$/u',
        'regexp_js' => '/^([\u4e00-\u9fa5a-zA-Z]+|[\u4e00-\u9fa5a-zA-Z]+([\u4e00-\u9fa5\w]*?)[\u4e00-\u9fa5a-zA-Z0-9])$/',
    ], REG_POSITIVE = [
        'label' => '正数',
        'unmatch' => '仅能为正数字包括小数',
        'eg' => '123.123',
        'regexp' => '/^\+?\d+(\.\d+)?$/',
    ], REG_MONTH = [
        'label' => '月份',
        'unmatch' => '请输入正确的月份',
        'eg' => '2016-07',
        'regexp' => '/^\d{4}-(?:0[1-9]|1[0-2])$/',
    ];

    public static function match($reg, $string) {
        return preg_match(is_string($reg) ? $reg : $reg['regexp'] ?? null, $string);
    }
}