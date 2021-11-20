<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/10 15:48
 */

namespace drunk\debug\storage;

/**
 * 文件版日志储存引擎
 * Class File
 * @package drunk\debug\storage\FileStorage
 */
class FileStorage extends DebugStorage {
    public function push(string $path, string $content, string $logType = self::LogTypeAppend): void {
        $filePath = $this->genPath($path);
        if (! is_dir($fileDir = dirname($filePath))) {
            mkdir($fileDir, 0766, true);
        }
        $content .= "\n";
        $logType === self::LogTypePrepend && $content .= file_get_contents($filePath) ?: '';
        file_put_contents($filePath, $content, ($logType === self::LogTypeAppend ? FILE_APPEND : 0) | LOCK_EX);
    }
}
