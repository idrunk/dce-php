<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/7 17:03
 */

namespace drunk\debug\output;

/**
 * 处理输出cli版表格
 * @package drunk\debug\output
 */
class CliDebug extends DebugStorable {
    protected function output(array $dataFormatted): void {
        $content = $this->format($dataFormatted);
        echo $content;
        $this->logStorage && $this->logStorage->push($this->getPath(), $content);
    }
}
