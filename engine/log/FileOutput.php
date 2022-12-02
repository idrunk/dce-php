<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/10 15:48
 */

namespace dce\log;

use dce\base\LogMethod;

/**
 * 文件版日志储存引擎
 */
class FileOutput extends MatrixOutput {
    public function push(string $path, string $content, LogMethod $logMethod = LogMethod::Append): void {
        $filePath = $this->genPath($path);
        ! is_dir($fileDir = dirname($filePath)) && mkdir($fileDir, 0766, true);
        $content .= "\n";
        $logMethod === LogMethod::Prepend && $content .= file_get_contents($filePath) ?: '';
        file_put_contents($filePath, $content, ($logMethod === LogMethod::Append ? FILE_APPEND : 0) | LOCK_EX);
    }
}
