<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-16 4:21
 */

namespace dce\sharding\id_generator;

use dce\base\Exception;
use dce\i18n\Language;

// 1650-1699
class IdgException extends Exception {
    // 运行时异常
    #[Language(['请先配置 %s'])]
    public const CONFIG_ITEM_MISSING = 1650;

    #[Language(['配置文件 %s 或 type 属性错误'])]
    public const PROPERTY_MAY_TYPE_ERROR = 1651;

    #[Language(['服务端必须配置单秒ID池:batch_bit_width'])]
    public const BATCH_BIT_WIDTH_MISSING = 1652;

    #[Language(['服务端未配置 %s 标签'])]
    public const TAG_CONFIG_MISSING_IN_SERVER = 1660;

    #[Language(['申请ID种子失败'])]
    public const BASE_ID_GENERATE_FAILED = 1661;

    #[Language(['从服务端申请的批次起始值异常'])]
    public const APPLIED_BATCH_FROM_INVALID = 1662;

    #[Language(['从服务端申请的批次截止值异常'])]
    public const APPLIED_BATCH_TO_INVALID = 1663;

    #[Language(['未取到时间ID种子time_id'])]
    public const BASE_TIME_ID_MISSING = 1664;
}
