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
    public static function camelize(string $name, bool $toUpper = false): string {
        $name = preg_replace_callback('/(?:[^\pL\pN]*)([\pL\pN]+)/ui', fn($matches) => ucfirst($matches[1]), $name);
        return $toUpper ? $name : lcfirst($name);
    }

    /**
     * 转蛇底式命名
     * @param string $name
     * @return string
     */
    public static function snakelike(string $name): string {
        return strtolower(preg_replace('/(?!^)\p{Lu}/ui', '_$0', $name));
    }

    /**
     * 将gb系列编码转为utf8编码
     * @param string $str
     * @return string
     */
    public static function gbToUtf8(string $str): string {
        return mb_convert_encoding($str, 'UTF-8', 'ASCII,UTF-8,GB2312,GBK') ;
    }

    /**
     * @param string $test
     * @return bool
     */
    public static function isRegexp(mixed $test): bool {
        return is_string($test) && preg_match('/^\/.+\/\w*$/u', $test);
    }

    /**
     * 对字符串脱敏
     * @param string $target
     * @param string $coverWith 用以替换的字符
     * @param int $left 左保留字符数（0表示不保留, 负数表示替换数）
     * @param int $right 右保留字符数（0表示不保留, 负数表示替换数）
     * @param int $middle 中间保留字符数（0表示不保留, 负数表示替换数）
     * @return string
     */
    public static function desensitise(string $target, string $coverWith = '*', int $left = 0, int $right = 3, int $middle = 0): string {
        $len = mb_strlen($target);
        $absLeft = abs($left);
        $absMiddle = abs($middle);
        $absRight = abs($right);
        $left < 0 && $target = substr_replace($target, str_repeat($coverWith, $absLeft), 0, $absLeft);
        $right < 0 && $target = substr_replace($target, str_repeat($coverWith, $absRight), $right);
        if ($middle) {
            $halfCover = ($len - $absLeft - $absRight - $absMiddle) / 2;
            $ceilHalfCover = ceil($halfCover);
            if ($middle > 0) {
                $target = substr_replace($target, str_repeat($coverWith, $ceilHalfCover), $absLeft, $ceilHalfCover);
                $target = substr_replace($target, str_repeat($coverWith, $ceilHalfCover), $absLeft + $ceilHalfCover + $absMiddle, $ceilHalfCover);
            } else {
                $target = substr_replace($target, str_repeat($coverWith, $absMiddle), $absLeft + $ceilHalfCover, $absMiddle);
            }
        }
        return $target;
    }

    /**
     * mb打乱字序
     * @param string $str
     * @return string
     */
    public static function shuffle(string $str): string {
        $chars = mb_str_split($str);
        shuffle($chars);
        return implode('', $chars);
    }

    /**
     * unicode转utf8编码字符
     * @param int $unicode
     * @return string
     */
    public static function unicodeToUtf8(int $unicode): string {
        $binary = decbin($unicode);
        $binaryLength = strlen($binary);
        if ($binaryLength < 8) {
            return chr($unicode);
        } else {
            $binaryParts = str_split(str_repeat('0', 6 - $binaryLength % 6) . $binary, 6);
            $byteCount = ceil($binaryLength / 6);
            return array_reduce(array_keys($binaryParts), fn(string $word, int $i) => $word .
                chr(($i ? 0b10000000 : bindec(str_repeat('1', $byteCount)) << (8 - $byteCount)) + bindec($binaryParts[$i])), '');
        }
    }
}
