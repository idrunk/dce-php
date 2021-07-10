<?php
/**
 * Author: Drunk
 * Date: 2020-05-14 15:17
 */

namespace dce\service\extender;

use dce\base\Exception;
use dce\i18n\Language;

// 1850-1899
class ExtenderException extends Exception {
    // 脚本错误
    #[Language(['sharding_extend.mapping配置错误, 缺少或有多余规则'])]
    public const INVALID_MAPPING_CONFIG = 1850;

    #[Language(['sharding_extend.mapping模值配置错误, 缺少 %s'])]
    public const MAPPING_CONFIG_MISSING_PROPERTY = 1851;

    #[Language(['扩展库sharding_extend.database配置错误, 无法与 sharding_extend.mapping.%s 对应'])]
    public const DATABASE_MAPPING_NOT_MATCH = 1852;

    // 运行时错误
    #[Language(['拓展库数据迁移失败'])]
    public const DATA_TRANSFER_FAILED = 1870;

    #[Language(['扩展配置尚未正式配置到分库配置中'])]
    public const EXTENDS_CONFIG_NOT_APPLIED = 1871;

    #[Language(['冗余数据清除失败'])]
    public const REDUNDANT_CLEAR_FAILED = 1872;

    #[Language(['拓展表不存在或创建失败'])]
    public const EXTEND_TABLE_CREATE_FAILED = 1873;

    #[Language(['拓展库数据迁移失败'])]
    public const EXTEND_DATA_TRANSFER_FAILED = 1874;

    #[Language(['请准备扩展库, 并创建扩展配置 %s_extend'])]
    public const PLEASE_PREPARE_EXTENDS_CONFIG = 1875;

    #[Language(['扩展库不存在'])]
    public const EXTEND_DATABASE_NOT_EXISTS = 1876;

    #[Language(['用户选择退出'])]
    public const USER_QUIT = 1877;
}
