<?php
/**
 * Author: Drunk
 * Date: 16-12-7 下午4:25
 */

namespace drunk;

use ArrayAccess;
use ArrayObject;
use SplFixedArray;

final class Structure {
    /**
     * 递归合并数组
     * @example arrayMerge([1, 'a'=>[2]], [2], ['a'=>[2]], ['b'=>[3]], ['b'=>4]); // [0=>1, 'a'=>[2, 2], 1=>2, 'b'=>4]
     * @param array $array
     * @param array $arrayToMerge
     * @param array ...$arrays
     * @return array
     */
    public static function arrayMerge(array $array, array $arrayToMerge, array ... $arrays): array {
        $isListArray = self::arrayIsList($arrayToMerge);
        foreach ($arrayToMerge as $k => $toMerge) {
            if ($isListArray) {
                // 如果是数字键, 则直接追加
                array_push($array, $toMerge);
            } else if (is_array($toMerge) && is_array($array[$k] ?? false)) {
                // 如果当前键在数组2中也存在, 且对应元素都为数组, 则递归合并
                $array[$k] = self::arrayMerge($array[$k], $toMerge);
            } else {
                // 否则直接覆盖或追加
                $array[$k] = $toMerge;
            }
        }
        foreach ($arrays as $toMerge) {
            $array = self::arrayMerge($array, $toMerge); // 递归合并所有数组
        }
        return $array;
    }

    /**
     * 用回调函数在数组中查找匹配值的下标集
     * @param callable $needle
     * @param array $haystack
     * @param bool $lazyMode 是否惰性查找（是则找到即返回下标，否则返回所有匹配的下标集）
     * @return array|string|int|false
     */
    public static function arraySearch(callable $needle, array $haystack, bool $lazyMode = true): array|string|int|false {
        $indexes = [];
        foreach ($haystack as $i => $item) {
            if (call_user_func($needle, $item)) {
                if ($lazyMode) return $i;
                $indexes[] = $i;
            }
        }
        return $indexes ?: false;
    }

    /**
     * 查询 参1 在 参2 数组元素中是否有相同矩阵值, 返回其在参2中的下标集
     * @param array $needle
     * @param array $haystack
     * @param bool $lazyMode  是否惰性模式
     * @return array|string|int|false
     */
    public static function arraySearchMatrix(array $needle, array $haystack, bool $lazyMode = true): array|string|int|false {
        $haystackItem = current($haystack);
        if (! $needle || ! $haystackItem) return false;
        $keysIntersect = array_intersect(array_keys($needle), array_keys($haystackItem));
        $indexes = [];
        foreach ($haystack as $k => $item) {
            foreach ($keysIntersect as $key) {
                if ($needle[$key] != $item[$key]) {
                    continue 2;
                }
            }
            if ($lazyMode) return $k;
            $indexes[] = $k;
        }
        return $indexes ?: false;
    }

    /**
     * 拆分解析字符串为根键与数组下标数组
     * @param string $key
     * @param string & $rootKey
     * @param array & $keyArray
     */
    public static function arraySplitKey(string $key, string & $rootKey, array|null & $keyArray) {
        $key = explode('.', $key);
        $keyArray = array_slice($key, 1);
        $rootKey = $key[0];
    }

    /**
     * 按结构数组下标对数组元素赋值
     * @param array $array
     * @param array $keyArray
     * @param mixed $value
     * @return array|false
     */
    public static function arrayAssign(array $array, array $keyArray, mixed $value): array|false {
        if (empty($keyArray) || ! is_array($keyArray)) {
            return false;
        }
        $result = & $array;
        foreach ($keyArray as $key) {
            if (! is_array($array)) {
                return false; // 如果值非数组, 则无法设置子值, 返回假
            }
            if (! array_key_exists($key, $array)) {
                $array[$key] = []; // 如果无子值, 则初始化为数组
            }
            $array = & $array[$key];
        }
        $array = $value;
        return $result;
    }

    /**
     * 按结构数组下标取数组元素
     * @param array $array
     * @param array $keyArray
     * @return mixed
     */
    public static function arrayIndexGet(array $array, array $keyArray): mixed {
        if (empty($keyArray) || ! is_array($keyArray)) {
            return null;
        }
        foreach ($keyArray as $key) {
            if (! is_array($array) || ! array_key_exists($key, $array)) {
                return null; // 如果值非数组或无子值, 则返回null
            }
            $array = $array[$key];
        }
        return $array;
    }

    /**
     * 按结构数组下标删除数组元素
     * @param array $array
     * @param array $keyArray
     * @return null|bool
     */
    public static function arrayIndexDelete(array & $array, array $keyArray): null|bool {
        if (empty($keyArray) || ! is_array($keyArray)) {
            return null;
        }
        $lastKey = count($keyArray) - 1;
        foreach ($keyArray as $k=> $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                break; // 如果值非数组或无子值, 则返回null
            }
            if ($lastKey === $k) {
                unset($array[$key]);
                return true;
            }
            $array = & $array[$key];
        }
        return false;
    }

    /**
     * 是否列表式数组
     * @param array|null $array
     * @return bool
     */
    public static function arrayIsList(array|null $array): bool{
        if (null === $array) {
            return false;
        }
        $keys = array_keys($array);
        $keysShouldBe = range(0, count($keys) - 1);
        return $keys === $keysShouldBe;
    }

    /**
     * 按照矩阵列值集为参考，将矩阵按该参考值列表排序（常用语in查询时，查询结果与in元素顺序不一致，此时可以用此方法方便的排序）
     * @param array[]|ArrayAccess[] $matrix 待排序的矩阵
     * @param string $column 排序参考列
     * @param array $refValues 排序参考值集
     * @return array
     */
    public static function sortByColumnRef(array $matrix, string $column, array $refValues): array {
        $valueOrderMapping = array_flip($refValues);
        $refOrderValues = [];
        foreach ($matrix as $array)
            $refOrderValues[] = $valueOrderMapping[$array[$column]] ?? 65535; // 没取到则往后排
        array_multisort($refOrderValues, SORT_ASC, SORT_NUMERIC, $matrix);
        return $matrix;
    }

    public static function arrayChangeKeyCase(array $array, bool|null $bigCamel = false): array {
        return array_reduce(array_keys($array), function($t, $k) use($array, $bigCamel) {
            $v = $array[$k];
            $t[$bigCamel === null ? Char::snakelike($k) : Char::camelize($k, $bigCamel)] = is_array($v) ? self::arrayChangeKeyCase($v, $bigCamel) : $v;
            return $t;
        }, []);
    }
}