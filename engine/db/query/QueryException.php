<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 14:27
 */

namespace dce\db\query;

use dce\base\Exception;
use dce\i18n\Language;

// 900-999
class QueryException extends Exception {
    #[Language(['缺少对应占位符的参数 %s'])]
    public const PLACEHOLDER_NOT_MATCH = 900;

    #[Language(['请勿使用混合占位符'])]
    public const NOT_ALLOW_MIXED_PLACEHOLDER = 901;

    #[Language(['表/字段名 %s 非法'])]
    public const TABLE_OR_COLUMN_INVALID = 902;

    #[Language(['非法table名'])]
    public const TABLE_NAME_INVALID = 910;

    #[Language(['分组字段 %s 无效'])]
    public const GROUP_COLUMN_INVALID = 911;

    #[Language(['字段 %s 非法'])]
    public const COLUMN_INVALID = 912;

    #[Language(['非法字段名 %s'])]
    public const COLUMN_NAME_INVALID = 913;

    #[Language(['非法select查询结构 %s'])]
    public const SELECT_STRUCT_INVALID = 914;

    #[Language(['INNER类型 %s 无效'])]
    public const INNER_TYPE_INVALID = 915;

    #[Language(['排序条件 %s 异常'])]
    public const ORDER_BY_INVALID = 916;

    #[Language(['无效的select修饰符 %s'])]
    public const SELECT_MODIFIER_INVALID = 917;

    #[Language(['筛选字段名 %s 无效'])]
    public const SELECT_COLUMN_INVALID = 918;

    #[Language(['非法table对象'])]
    public const TABLE_INVALID = 919;

    #[Language(['非法 %s 运算右值:\n%s'])]
    public const RIGHT_VALUE_INVALID = 921;

    #[Language(['非法比较运算符 %s'])]
    public const COMPARE_OPERATOR_INVALID = 922;

    #[Language(['查询条件无效, 左比较值 %s 非法'])]
    public const LEFT_COMPARE_VALUE_INVALID = 923;

    #[Language(['查询条件无效, 左比较值非法'])]
    public const LEFT_SPECIAL_COMPARE_VALUE_INVALID = 924;

    #[Language(['查询条件无效, 非查询条件或逻辑运算符'])]
    public const WHERE_OR_LOGIC_CONDITION_INVALID = 925;

    #[Language(['未配置删除表'])]
    public const DELETE_TABLE_NOT_SPECIFIED = 950;

    #[Language(['多表无法排序删除指定条数'])]
    public const CANNOT_DELETE_WITH_MULTIPLE_SORTED_TABLE = 951;

    #[Language(['待删数据表不支持指定别名'])]
    public const DELETE_TABLE_NOT_SUPPORT_ALIAS = 952;

    #[Language(['当前设置不允许空条件删除全表'])]
    public const EMPTY_DELETE_FULL_NOT_ALLOW = 953;

    #[Language(['当前设置不允许无等于条件删除数据'])]
    public const NO_EQUAL_DELETE_NOT_ALLOW = 954;

    #[Language(['未配置插入表'])]
    public const INSERT_TABLE_NOT_SPECIFIED = 955;

    #[Language(['未配置select查询实体'])]
    public const SELECT_TABLE_NOT_SPECIFIED = 956;

    #[Language(['未传入插入数据'])]
    public const NO_INSERT_DATA = 957;

    #[Language(['无效查询语句, 缺少查询字段'])]
    public const SELECT_COLUMN_MISSED = 958;

    #[Language(['未配置更新表'])]
    public const UPDATE_TABLE_NOT_SPECIFIED = 959;

    #[Language(['未传入更新数据'])]
    public const NO_UPDATE_DATA = 960;

    #[Language(['多表无法排序更新指定条数'])]
    public const CANNOT_UPDATE_WITH_MULTIPLE_SORTED_TABLE = 961;

    #[Language(['当前设置不允许空条件更新全表'])]
    public const EMPTY_UPDATE_FULL_NOT_ALLOW = 962;

    #[Language(['当前设置不允许无等于条件更新数据'])]
    public const NO_EQUAL_UPDATE_NOT_ALLOW = 963;

    #[Language(['ID %s 无法匹配到对应的分库, 数据无法完成插入'])]
    public const ID_CANNOT_MATCH_DB = 980;
}
