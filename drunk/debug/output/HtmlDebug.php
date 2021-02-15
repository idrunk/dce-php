<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/7 16:47
 */

namespace drunk\debug\output;

use drunk\debug\DebugMatrix;

/**
 * 处理输出html版表格
 * Class Html
 * @package drunk\debug\output
 */
final class HtmlDebug extends DebugMatrix {
    /**
     * 处理调试数据输出html表格
     * @param array $dataFormatted
     */
    protected function output(array $dataFormatted): void {
        $html = '<table class="drunk-test"><thead><tr><th class="number">序号</th><th class="line">行号</th><th class="filename">文件名</th>'
            . '<th class="time-step">步耗时</th><th class="time-total">总耗时</th><th>执行时间</th></tr></thead>' . "\n\n";
        foreach($dataFormatted as $v) {
            $title = '序      号：'.$v['number'].'&#10;'.
                '行      号：'.$v['line'].'&#10;'.
                '文  件 名：'.$v['filename'].'&#10;'.
                '步耗毫秒：'.$v['time_step'].'&#10;'.
                '总耗毫秒：'.$v['time_total'].'&#10;'.
                '文件路径：'.$v['file'];
            $html .= '<tbody title="'.$title.'"><tr class="dt-head dt-head-'.($v['number']%2).'"><th>'.$v['number'].'</th><th>'.$v['line']
                .'</th><th>'.$v['filename'].'</th><th>'.$v['time_step'].'</th><th>'.$v['time_total']."</th><th>".$v['time_string']."</th></tr>\n\n";
            $html .= $this->makeMark($v);
        }
        $html .= '</table>';
        self::show($html);
    }

    /**
     * 点位标记调试信息处理
     * @param array $point
     * @return string
     */
    private function makeMark(array $point): string {
        $tab = '';
        foreach($point['args_formatted'] as $k=>$v) {
            // 编码xml标签为可视字符
            $content = htmlspecialchars($v, ENT_QUOTES);
            $tab .= '<tr class="dt-arg dt-arg-' .($k%2). '"><th class="number">'. $point['number'] .'-'. ($k+1)
                . '</th><td colspan="6"><pre contenteditable="true">' ."\n$content\n</pre></td></tr>\n\n";
        }
        return $tab . '</tbody>';
    }

    /**
     * 输出内容 (错未输出过, 则包裹上html布局, 否则直接输出)
     * @param $html
     */
    private function show(string $html): void {
        self::header();
        self::style();
        echo $html;
        self::footer();
    }

    /**
     * 输出html头
     */
    private static function header(): void {
        static $isDefined;
        if (! $isDefined) {
            $isDefined = 1;
            if (! ob_get_length()) {
                echo '<!doctype html><html lang="zh"><head><meta charset="utf-8" /><title>Drunk Debug</title>'
                    . '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"></head><body>';
            }
        }
    }

    private static function footer(): void {}

    /**
     * 加载非源码形式css样式
     */
    private static function style(): void {
        static $isDefined;
        if (! $isDefined) {
            $isDefined = 1;
            echo '<style type="text/css">'
                .'.drunk-test{border-collapse:collapse;font-size:12px;width:auto !important;}'
                .'.drunk-test thead th{background:#e9e9e9;padding:5px 10px;}'
                .'.drunk-test th.number{width:40px;}'
                .'.drunk-test th.line{width:40px;}'
                .'.drunk-test th.filename{width:256px;}'
                .'.drunk-test th.time-micro-step{width:60px;}'
                .'.drunk-test th.time-step{width:40px;}'
                .'.drunk-test th.time-total{width:40px;}'
                .'.drunk-test tbody tr:hover th,.drunk-test tbody tr:hover td{background:#fefefe;}'
                .'.drunk-test th,.drunk-test td{border:1px solid #e3e3e3;padding:3px 5px;}'
                .'.drunk-test .dt-head th{background:#eee;font-weight:400;}'
                .'.drunk-test .dt-head-1 th{background:#f2f2f2;}'
                .'.drunk-test .dt-arg th{border-color:#e9e9e9;background:#f3f3f3;font-weight:400;}'
                .'.drunk-test .dt-arg-1 th{background:#efefef;}'
                .'.drunk-test .dt-arg td{border-color:#eee;background:#fefefe;font-weight:400;text-align:left !important;}'
                .'.drunk-test .dt-arg-1 td{background:#fcfcfc;}'
                .'.drunk-test pre{line-height:20px;word-wrap:break-word;word-break:break-all;margin:0;max-height:800px;overflow-x:hidden !important;overflow-y:auto !important;}'
                .'::-webkit-scrollbar{width:4px;height:4px;}'
                .'::-webkit-scrollbar-thumb{border-radius:10px;box-shadow:inset 0 0 5px rgba(0,0,0,.2);background:rgba(0,0,0,.2);}'
                .'::-webkit-scrollbar-track{box-shadow:inset 0 0 5px rgba(0,0,0,.2);border-radius: 0;background:rgba(0,0,0,.1);}'
                ."</style>\n\n\n";
        }
    }
}
