<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 14:32
 */

namespace dce\sharding\middleware;

use dce\base\Exception;
use dce\i18n\Language;

// 1800-1849
class MiddlewareException extends Exception {
    // 脚本异常
    #[Language(['分库配置错误, 分库类型异常或未配置'])]
    public const CONFIG_SHARDING_TYPE_INVALID = 1800;

    #[Language(['缺少分库配置 %s > %s > id_column'])]
    public const CONFIG_ID_COLUMN_EMPTY = 1801;

    #[Language(['缺少分库配置 %s > %s > sharding_column'])]
    public const CONFIG_SHARDING_COLUMN_EMPTY = 1802;

    #[Language(['分库配置错误, 未配置 %s 表内容切分依据字段'])]
    public const CONFIG_TABLE_SHARDING_RULE_EMPTY = 1803;

    // 运行时异常
    #[Language(['非按模分库禁止连表查询'])]
    public const NO_MOD_SHARDING_NO_JOINT = 1820;

    #[Language(['未开启分库连表查询 (可以设置allow_joint=true开启)'])]
    public const ALLOW_JOINT_NOT_OPEN = 1821;

    #[Language(['分库配置错误, 未在储存数据中找到作为ID基因的字段 %s'])]
    public const GENE_COLUMN_NOT_FOUND = 1822;

    #[Language(['禁止更新分库依据字段，若允许请开启 cross_update 参数。该操作较危险，请谨慎。（建议自己实现跨库迁移功能）'])]
    public const OPEN_CROSS_UPDATE_TIP = 1823;

    #[Language(['记录插入失败，获取新ID失败'])]
    public const INSERT_FAILED_NO_ID = 1824;

    #[Language(['分库插入错误，未配置idTag时需手动指定待插入ID %s'])]
    public const INSERT_ID_NOT_SPECIFIED = 1825;

    #[Language(['分库依据字段 %s 未指定有效值'])]
    public const SHARDING_COLUMN_NOT_SPECIFIED = 1826;

    #[Language(['表 %s 中不存在分表依据字段 %s，或该字段有无效值，无法进行迁移更新'])]
    public const SHARDING_VALUE_NOT_SPECIFIED = 1827;

    #[Language(['跨库更新表 %s 时需指定idColumn字段 %s 的值'])]
    public const UP_ID_VALUE_NOT_SPECIFIED = 1828;

    #[Language(['跨库更新表 %s 时需指定shardingColumn字段 %s 的值'])]
    public const UP_SHARDING_VALUE_NOT_SPECIFIED = 1829;
}
