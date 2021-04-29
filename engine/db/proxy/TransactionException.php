<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/09/18 17:37
 */

namespace dce\db\proxy;

use dce\base\Exception;
use dce\i18n\Language;

// 1080-1099
class TransactionException extends Exception {
    #[Language(['TransactionSimple不支持Swoole协程环境'])]
    public const CANNOT_RUN_IN_COROUTINE = 1080;

    #[Language(['已开启该库事务, 请勿重复开启'])]
    public const REPEATED_OPEN = 1081;

    #[Language(['TransactionSharding仅支持协程环境'])]
    public const NEED_RUN_IN_COROUTINE = 1090;

    #[Language(['不支持跨分库事务'])]
    public const NOT_SUPPORT_SHARDING_TRANSACTION = 1091;
}