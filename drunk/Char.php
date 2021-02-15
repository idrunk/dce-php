<?php
/**
 * Author: Drunk
 * Date: 2017-1-20 21:31
 */

namespace drunk;

class Char {
    /**
     * 驼峰法命名
     * @param string $name
     * @param bool $toUpper
     * @return string
     */
    public static function camelize (string $name, bool $toUpper = false): string {
        $name = preg_replace_callback('/(?:[^\pL\pN]*)([\pL\pN]+)/ui', fn ($matches) => ucfirst($matches[1]), $name);
        return $toUpper ? $name : lcfirst($name);
    }

    /**
     * 转蛇底式命名
     * @param string $name
     * @return string
     */
    public static function snakelike (string $name): string {
        return strtolower(preg_replace('/(?!^)\p{Lu}/ui', '_$0', $name));
    }

    /**
     * 将gb系列编码转为utf8编码
     * @param string $str
     * @return string
     */
    public static function gbToUtf8 (string $str): string {
        return mb_convert_encoding($str, 'UTF-8', 'ASCII,UTF-8,GB2312,GBK') ;
    }

    /**
     * @param string $test
     * @return bool
     */
    public static function isRegexp (string $test): bool {
        return preg_match('/^\/.+\/\w*$/', $test);
    }
}
