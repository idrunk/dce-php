<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/1 17:43
 */

namespace drunk;

use ArrayAccess;
use dce\base\BaseException;

final class Utility {
    /**
     * 判断值是否数组或可以按数组式访问
     * @param mixed $value
     * @return bool
     */
    public static function isArrayLike(mixed $value): bool {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * 递归判断是否为空
     * @param mixed $object
     * @return bool
     */
    public static function isEmpty(mixed $object): bool {
        return empty($object) || (is_array($object) && self::isEmptyArray($object));
    }

    /**
     * 判断是否空数组（无元素、或仅有空数组的元素，视为空数组）
     * @param array $array
     * @return bool
     */
    private static function isEmptyArray(array $array): bool {
        foreach ($array as $item)
            if (! is_array($item) || ($item && ! self::isEmptyArray($item))) return false;
        return true;
    }

    /**
     * 将数据转为可打印的类型并返回
     * @param mixed $value
     * @return string
     */
    public static function printable(mixed $value): string {
        if (is_object($value)) {
            $value = get_class($value);
        } else if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $len = mb_strlen($value = (string) $value);
        static $maxCut = 1024;
        return mb_substr($value, 0, $len > $maxCut ? $maxCut : $len) . ($len > $maxCut ? '...' : '');
    }

    public static function buildInstance(string $className, array $arguments = []): object|false {
        if (class_exists($className)) {
            return new $className(... $arguments);
        }
        return false;
    }

    public static function noop(): callable {
        static $noop;
        $noop ??= fn() => null;
        return $noop;
    }

    /**
     * 限制静态方法必须在子类执行
     * @param string $parentClass
     * @param string $staticClass
     * @throws BaseException
     */
    public static function staticConstraint(string $parentClass, string $staticClass): void {
        $parentClass == $staticClass && throw new BaseException(BaseException::NEED_CHILD_STATIC);
    }
}
