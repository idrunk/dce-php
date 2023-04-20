<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 14:27
 */

namespace dce\db\query;

use dce\base\Exception;
use dce\i18n\Language;

// 1300-1399
class QueryException extends Exception {
    #[Language(['缺少对应占位符的参数 %s'])]
    public const PLACEHOLDER_NOT_MATCH = 1300;

    #[Language(['请勿使用混合占位符'])]
    public const NOT_ALLOW_MIXED_PLACEHOLDER = 1301;

    #[Language(['表/字段名 %s 非法'])]
    public const TABLE_OR_COLUMN_INVALID = 1302;

    #[Language(['非法table名'])]
    public const TABLE_NAME_INVALID = 1310;

    #[Language(['分组字段 %s 无效'])]
    public const GROUP_COLUMN_INVALID = 1311;

    #[Language(['字段 %s 非法'])]
    public const COLUMN_INVALID = 1312;

    #[Language(['非法字段名 %s'])]
    public const COLUMN_NAME_INVALID = 1313;

    #[Language(['非法select查询结构 %s'])]
    public const SELECT_STRUCT_INVALID = 1314;

    #[Language(['INNER类型 %s 无效'])]
    public const INNER_TYPE_INVALID = 1315;

    #[Language(['排序条件 %s 异常'])]
    public const ORDER_BY_INVALID = 1316;

    #[Language(['无效的select修饰符 %s'])]
    public const SELECT_MODIFIER_INVALID = 1317;

    #[Language(['筛选字段名 %s 无效'])]
    public const SELECT_COLUMN_INVALID = 1318;

    #[Language(['非法table对象'])]
    public const TABLE_INVALID = 1319;

    #[Language(['非法 %s 运算右值:\n%s'])]
    public const RIGHT_VALUE_INVALID = 1321;

    #[Language(['非法比较运算符 %s'])]
    public const COMPARE_OPERATOR_INVALID = 1322;

    #[Language(['查询条件无效, 左比较值 %s 非法'])]
    public const LEFT_COMPARE_VALUE_INVALID = 1323;

    #[Language(['查询条件无效, 左比较值非法'])]
    public const LEFT_SPECIAL_COMPARE_VALUE_INVALID = 1324;

    #[Language(['查询条件无效, 非查询条件或逻辑运算符'])]
    public const WHERE_OR_LOGIC_CONDITION_INVALID = 1325;

    #[Language(['无效的窗口函数滑块结构'])]
    public const WINDOW_FRAME_INVALID = 1340;

    #[Language(['未配置删除表'])]
    public const DELETE_TABLE_NOT_SPECIFIED = 1350;

    #[Language(['多表无法排序删除指定条数'])]
    public const CANNOT_DELETE_WITH_MULTIPLE_SORTED_TABLE = 1351;

    #[Language(['待删数据表不支持指定别名'])]
    public const DELETE_TABLE_NOT_SUPPORT_ALIAS = 1352;

    #[Language(['当前设置不允许空条件删除全表'])]
    public const EMPTY_DELETE_FULL_NOT_ALLOW = 1353;

    #[Language(['当前设置不允许无等于条件删除数据'])]
    public const NO_EQUAL_DELETE_NOT_ALLOW = 1354;

    #[Language(['未配置插入表'])]
    public const INSERT_TABLE_NOT_SPECIFIED = 1355;

    #[Language(['未配置select查询实体'])]
    public const SELECT_TABLE_NOT_SPECIFIED = 1356;

    #[Language(['未传入插入数据'])]
    public const NO_INSERT_DATA = 1357;

    #[Language(['无效查询语句, 缺少查询字段'])]
    public const SELECT_COLUMN_MISSED = 1358;

    #[Language(['未配置更新表'])]
    public const UPDATE_TABLE_NOT_SPECIFIED = 1359;

    #[Language(['未传入更新数据'])]
    public const NO_UPDATE_DATA = 1360;

    #[Language(['多表无法排序更新指定条数'])]
    public const CANNOT_UPDATE_WITH_MULTIPLE_SORTED_TABLE = 1361;

    #[Language(['当前设置不允许空条件更新全表'])]
    public const EMPTY_UPDATE_FULL_NOT_ALLOW = 1362;

    #[Language(['当前设置不允许无等于条件更新数据'])]
    public const NO_EQUAL_UPDATE_NOT_ALLOW = 1363;

    #[Language(['冲突时更新字段不能为空'])]
    public const CONFLICT_UPDATE_COLUMNS_CANNOT_BE_EMPTY = 1364;

    #[Language(['ID %s 无法匹配到对应的分库, 数据无法完成插入'])]
    public const ID_CANNOT_MATCH_DB = 1380;

    #[Language(['PDO::prepare() 超时'])]
    public const PDO_PREPARE_TIMEOUT = 1395;
}
