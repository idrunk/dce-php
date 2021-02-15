<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 2:11
 */

namespace drunk;

class File {
    public static function write (string $filename, string $content, string $mode='wb'): bool {
        if (! file_exists(dirname($filename))) {
            mkdir(dirname($filename), 0777, true);
        }
        if (false === ($file = fopen($filename, $mode))) {
            return false;
        }
        fwrite($file, $content);
        return fclose($file);
    }

    public static function listFile ($pathsRoot, $match = null, $filter = null, int $depth = 0): array {
        return self::list($pathsRoot, $match, $filter, $depth, 'file');
    }

    public static function listDir ($pathsRoot, $match = null, $filter = null, int $depth = 0): array {
        return self::list($pathsRoot, $match, $filter, $depth, 'dir');
    }

    /**
     * 取目录文件列表
     * @param string|array $roots
     * @param string|array $match
     * @param string|array $filter
     * @param int $depth
     * @param string $type
     * @return array
     */
    public static function list ($roots, $match = null, $filter = null, int $depth = 0, string $type = 'all'): array {
        if (empty($match)) {
            $match = [];
        }
        if (empty($filter)) {
            $filter = [];
        }
        if (! array_key_exists($type, ['file'=>1, 'dir'=>1])) {
            $type = 'all';
        }
        $lists = [];
        foreach ((array) $roots as $root){
            $list = self::listRecursion($root, (array) $match, (array) $filter, (int) $depth, $type);
            if (!empty($list)) {
                $lists = array_merge($lists, $list);
            }
        }
        return $lists;
    }

    private static function listRecursion ($root, $match, $filter, $depth, $type, $level = 1) {
        $isDeepest = $depth === $level;
        $listInRoot = scandir($root);

        if (false === $listInRoot) {
            return false;
        }
        $listInRoot = array_filter($listInRoot, function($fileName){ // 过滤掉当前与上级目录名
            return ! array_key_exists($fileName, ['.'=>1, '..'=>1]);
        });
        if (empty($listInRoot)) {
            return null;
        }

        $list = [];
        $root = realpath($root);
        foreach ($listInRoot as $k=>$v) {
            $filePath = $root . DIRECTORY_SEPARATOR . $v;
            if (self::fnmatchArray($filter, $filePath)) {
                continue; // 过滤
            }
            if (is_dir($filePath)){
                $isMatched = empty($match) || self::fnmatchArray($match, $filePath);
                if ($isDeepest) { // 若为最深匹配
                    if (! $isMatched || $type === 'file') {
                        continue; // 若未匹配到, 则跳过
                    }
                    $list[] = $filePath;
                } else {
                    $listOfChildren = self::listRecursion($filePath, $match, $filter, $depth, $type, $level + 1);
                    if (empty($listOfChildren)) {
                        if ($isMatched && $type !== 'file') {
                            $list[] = $filePath;
                        }
                    } else {
                        if ($type !== 'file') {
                            $list[] = $filePath;
                        }
                        $list = array_merge($list, $listOfChildren);
                    }
                }
            } else {
                if ($type === 'dir' || (!empty($match) && !self::fnmatchArray($match, $filePath))) {
                    continue; // 若未匹配, 则跳过
                }
                $list[] = $filePath;
            }
        }
        return $list;
    }

    private static function fnmatchArray ($patterns, $string) {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $string, FNM_NOESCAPE)) {
                return true;
            }
        }
        return false;
    }
}