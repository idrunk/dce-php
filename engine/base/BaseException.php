<?php
/**
 * Author: Drunk
 * Date: 2021-4-21 1:28
 */

namespace dce\base;

use dce\i18n\Language;
use dce\loader\StaticInstance;

// 1100-1119
class BaseException extends Exception implements StaticInstance {
    public const LANGUAGE_MAPPING_ERROR = 1100;
    #[StaticInstance(self::LANGUAGE_MAPPING_ERROR)]
    private static Language|array $LANGUAGE_MAPPING_ERROR = ['语种映射表配置错误'];

    public const LANGUAGE_MAPPING_CALLABLE_ERROR = 1101;
    #[StaticInstance(self::LANGUAGE_MAPPING_CALLABLE_ERROR)]
    private static Language|array $LANGUAGE_MAPPING_CALLABLE_ERROR = ['语种映射工厂配置错误或工厂方法未返回语种文本映射表'];

    public const NEED_ROOT_PROCESS = 1110;
    #[StaticInstance(self::NEED_ROOT_PROCESS)]
    private static Language|array $NEED_ROOT_PROCESS = ['当前过程需在根进程中执行'];

    public const MESSAGE_NOT_LANGUAGE = 1111;
    #[StaticInstance(self::MESSAGE_NOT_LANGUAGE)]
    private static Language|array $MESSAGE_NOT_LANGUAGE = ['异常消息非Language对象'];
}