<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/10 15:41
 */

namespace drunk\debug\output;

use drunk\debug\DebugMatrix;
use drunk\debug\storage\DebugStorage;

abstract class DebugStorable extends DebugMatrix {
    /** @var string 填充符 */
    private static string $fillerChar = ' ';

    protected DebugStorage|null $logStorage = null;

    private string $path;

    /**
     * 格式化文本表格
     * @param array $data
     * @return string
     */
    protected function format (array $data): string {
        // 表格主体框架 (用于后续计算填补宽度)
        $tableFramework = [ ['序号', '行号', '文件名', '步耗时', '总耗时'], ];
        foreach ($data as $v) {
            $tableFramework[] = [$v['number'], $v['line'], $v['file']];
            $tableFramework[] = [$v['number'] .'-'. count($v['args'])];
        }
        // 将框架转为竖表
        $columnTable = self::rowToColumn($tableFramework);
        // 根据竖表计算获取列最宽值字典
        $widthMap = self::maxWidthMap($columnTable);
        // 拼接表头并补宽度
        $content = self::fillColumn('序号', $widthMap[0])
            . self::fillColumn('行号', $widthMap[1])
            . self::fillColumn('文件名', $widthMap[2])
            . self::fillColumn('步耗时', $widthMap[3])
            . self::fillColumn('总耗时', $widthMap[4])
            . "执行时间\n";
        foreach ($data as $k=>$v) {
            // 处理拼接标记点
            $content .= $this->makeMark($v, $widthMap);
        }
        return $content . "\n";
    }

    /**
     * 处理标记信息及标记值
     * @param array $point
     * @param array $width_map
     * @return string
     */
    private static function makeMark (array $point, array $width_map): string {
        // 标记点头信息
        $part = self::fillColumn($point['number'], $width_map[0])
            . self::fillColumn($point['line'], $width_map[1])
            . self::fillColumn($point['file'], $width_map[2])
            . self::fillColumn($point['time_step'], $width_map[3])
            . self::fillColumn($point['time_total'], $width_map[4])
            . "$point[time_string]\n";
        foreach($point['args_formatted'] as $k=>$v) {
            // 处理多行内容, 将非首行内容前补填充符, 美化表格
            $content = preg_replace('/(?<=[\r\n])/', self::fillColumn('', $width_map[0]), $v);
            $part .= self::fillColumn($point['number'] .'-'. ($k+1), $width_map[0]) . $content ."\n";
        }
        return $part;
    }

    /**
     * 将横表转竖表
     * @param array $table
     * @return array
     */
    private static function rowToColumn(array $table): array {
        $converted = [];
        foreach ($table as $row) {
            $i = 0;
            foreach ($row as $v) {
                $converted[$i][] = $v;
                $i ++;
            }
        }
        return $converted;
    }

    /**
     * 根据竖表取列最宽值串的字典
     * @param array $columnTable
     * @param int $intervalSpace
     * @return array
     */
    private static function maxWidthMap(array $columnTable, int $intervalSpace = 3): array {
        $widthMap = [];
        foreach ($columnTable as $i=> $column) {
            $maxWidth = 0;
            foreach ($column as $v) {
                if ($maxWidth < $width = self::stringWidth($v)) {
                    $maxWidth = $width;
                }
            }
            $widthMap[$i] = $maxWidth + $intervalSpace;
        }
        return $widthMap;
    }

    /**
     * 取字符串宽度 (ascii算1个宽度, 非ascii算两个宽度)
     * @param string $string
     * @return float|int
     */
    private static function stringWidth(string $string): float|int {
        $len = mb_strlen($string);
        // 取ascii字符长度
        $asciiLen = strlen(preg_replace('/[^\x00-\xff]/u', '', $string));
        $width = $asciiLen + ($len - $asciiLen) * 2;
        return $width;
    }

    /**
     * 对字符串补填充符, 使其达到最大宽度
     * @param string $string
     * @param int $maxWidth
     * @return string
     */
    private static function fillColumn(string $string, int $maxWidth): string {
        $stringWidth = self::stringWidth($string);
        $tabDiff = $maxWidth - $stringWidth;
        if ($tabDiff > 0) {
            $string .= str_repeat(self::$fillerChar, $tabDiff);
        }
        return $string;
    }

    /**
     * 设置储存引擎
     * @param DebugStorage $storage
     * @return mixed
     */
    public function setStorage(DebugStorage $storage): static {
        $this->logStorage = $storage;
        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setPath(string $path): static {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string
     */
    protected function getPath(): string {
        $path = $this->path;
        unset($this->path);
        return $path;
    }
}
