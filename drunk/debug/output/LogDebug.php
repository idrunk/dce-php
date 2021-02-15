<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/7 17:03
 */

namespace drunk\debug\output;

use drunk\debug\DebugException;

/**
 * 处理记录标记log
 * Class Log
 * @package drunk\debug\output
 */
class LogDebug extends DebugStorable {
    public function apply(array $data): self {
        $this->data = $data;
        return $this;
    }

    protected function output(array $dataFormatted): void {
        if (! $this->logStorage) {
            throw new DebugException('请先调用setStorage设置储存引擎');
        }
        $content = $this->format($dataFormatted);
        $this->logStorage->push($this->getPath(), $content); // 储存调试内容
    }
}
