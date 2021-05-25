<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/8 18:28
 */

namespace drunk\debug;

use dce\base\Exception;
use dce\i18n\Language;

// 1190-1199
class DebugException extends Exception {
    #[Language(['无效终端名 %s'])]
    public const INVALID_END = 1190;

    #[Language(['储存路径无效'])]
    public const INVALID_STORAGE_PATH = 1191;

    #[Language(['储存引擎名 %s 无效'])]
    public const INVALID_STORAGE_NAME = 1192;

    #[Language(['获取上下文失败，请勿包装debug方法'])]
    public const GET_CONTEXT_FAILED = 1193;

    #[Language(['Point不能用在非Cli模式下'])]
    public const CLI_NOT_SUPPORT_POINT = 1194;

    #[Language(['请先调用setStorage设置储存引擎'])]
    public const SET_STORAGE_FIRST = 1195;
}
