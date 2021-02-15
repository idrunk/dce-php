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
    public function push(string $path, string $content): void {
        $filePath = $this->genPath($path);
        if (! is_dir($fileDir = dirname($filePath))) {
            mkdir($fileDir, 0766, true);
        }
        $content .= "\n";
        file_put_contents($filePath, $content, FILE_APPEND | LOCK_EX);
    }
}
