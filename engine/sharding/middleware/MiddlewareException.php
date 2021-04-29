<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 14:32
 */

namespace dce\sharding\middleware;

use dce\base\Exception;
use dce\i18n\Language;

// 1400-1449
class MiddlewareException extends Exception {
    // 脚本异常
    #[Language(['分库配置错误, 分库类型异常或未配置'])]
    public const CONFIG_SHARDING_TYPE_INVALID = 1400;

    #[Language(['分库配置 %s > %s > id_column 异常'])]
    public const CONFIG_ID_COLUMN_INVALID = 1401;

    #[Language(['分库配置 %s > %s > sharding_column 异常'])]
    public const CONFIG_SHARDING_COLUMN_INVALID = 1402;

    #[Language(['分库配置错误, 未配置 %s 表内容切分依据字段'])]
    public const CONFIG_TABLE_SHARDING_RULE_EMPTY = 1403;

    // 运行时异常
    #[Language(['非按模分库禁止连表查询'])]
    public const NO_MOD_SHARDING_NO_JOINT = 1420;

    #[Language(['未开启分库连表查询 (可以设置allow_joint=true开启)'])]
    public const ALLOW_JOINT_NOT_OPEN = 1421;

    #[Language(['分库配置错误, 未在储存数据中找到作为ID基因的字段 %s'])]
    public const GENE_COLUMN_NOT_FOUND = 1422;

    #[Language(['禁止更新分库依据字段，若允许请开启 cross_update 参数。该操作较危险，请谨慎。（建议自己实现跨库迁移功能）'])]
    public const OPEN_CROSS_UPDATE_TIP = 1423;
}
