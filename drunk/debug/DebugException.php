<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/8 18:28
 */

namespace drunk\debug;

use dce\base\Exception;
use dce\i18n\Language;

class DebugException extends Exception {
    #[Language(['无效终端名 %s'])]
    public const INVALID_END = 790;

    #[Language(['储存路径无效'])]
    public const INVALID_STORAGE_PATH = 791;

    #[Language(['储存引擎名 %s 无效'])]
    public const INVALID_STORAGE_NAME = 792;

    #[Language(['获取上下文失败，请勿包装debug方法'])]
    public const GET_CONTEXT_FAILED = 793;

    #[Language(['Point不能用在非Cli模式下'])]
    public const CLI_NOT_SUPPORT_POINT = 794;

    #[Language(['请先调用setStorage设置储存引擎'])]
    public const SET_STORAGE_FIRST = 795;
}
