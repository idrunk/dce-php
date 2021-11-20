<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/10 15:46
 */

namespace drunk\debug\storage;

/**
 * 储存引擎接口
 * Interface StorageInterface
 */
abstract class DebugStorage {
    public const LogTypeAppend = 'append';
    public const LogTypeReplace = 'replace';
    public const LogTypePrepend = 'prepend';

    public function __construct(
        protected string $root,
    ) {
        $this->root = preg_replace('/\/+?$/', '/', $this->root);
    }

    /**
     * 拼储存路径
     * @param string $path
     * @return string
     */
    protected function genPath(string $path): string {
        return $this->root . ltrim($path, '/');
    }

    /**
     * 压入调试内容
     * @param string $path
     * @param string $content
     * @param string $logType
     */
    abstract public function push(string $path, string $content, string $logType = self::LogTypeAppend): void;
}
