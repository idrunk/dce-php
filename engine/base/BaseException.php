<?php
/**
 * Author: Drunk
 * Date: 2021-4-21 1:28
 */

namespace dce\base;

use dce\i18n\Language;
use dce\loader\attr\Constructor;

// 1100-1119
class BaseException extends Exception {
    public const LANGUAGE_MAPPING_ERROR = 1100;
    #[Constructor(self::LANGUAGE_MAPPING_ERROR)]
    private static Language|array $LANGUAGE_MAPPING_ERROR = ['语种映射表配置错误'];

    public const LANGUAGE_MAPPING_CALLABLE_ERROR = 1101;
    #[Constructor(self::LANGUAGE_MAPPING_CALLABLE_ERROR)]
    private static Language|array $LANGUAGE_MAPPING_CALLABLE_ERROR = ['语种映射工厂配置错误或工厂方法未返回语种文本映射表'];

    public const NEED_ROOT_PROCESS = 1110;
    #[Constructor(self::NEED_ROOT_PROCESS)]
    private static Language|array $NEED_ROOT_PROCESS = ['当前过程需在根进程中执行'];

    public const MESSAGE_NOT_LANGUAGE = 1111;
    #[Constructor(self::MESSAGE_NOT_LANGUAGE)]
    private static Language|array $MESSAGE_NOT_LANGUAGE = ['异常消息非Language对象'];

    public const NEED_CHILD_STATIC = 1112;
    #[Constructor(self::NEED_CHILD_STATIC)]
    private static Language|array $NEED_CHILD_STATIC = ['当前静态方法需通过子类调用'];
}