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
     * @param int $leftKeeps 左保留字符数（0表示不保留）
     * @param int $rightKeeps 右保留字符数
     * @param int $middleKeeps 中间保留字符数
     * @return string
     */
    public static function desensitise(string $target, string $coverWith = '*', int $leftKeeps = 0, int $rightKeeps = 3, int $middleKeeps = 0): string {
        $len = mb_strlen($target);
        if ($middleKeeps > 0) {
            $halfCover = ($len - $leftKeeps - $rightKeeps - $middleKeeps) / 2;
            $target = mb_substr($target, 0, $leftKeeps) . str_repeat($coverWith, floor($halfCover)) . mb_substr($target, $leftKeeps + floor($halfCover), $middleKeeps) . str_repeat($coverWith, ceil($halfCover)) . mb_substr($target, - $rightKeeps);
        } else {
            $coverLen = $len - $leftKeeps - $rightKeeps;
            $target = mb_substr($target, 0, $leftKeeps) . str_repeat($coverWith, $coverLen) . mb_substr($target, - $rightKeeps);
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
