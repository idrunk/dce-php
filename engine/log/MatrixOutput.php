<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/10 15:46
 */

namespace dce\log;

use dce\base\LogMethod;

/**
 * 储存引擎接口
 * Interface StorageInterface
 */
abstract class MatrixOutput {
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
     * @param LogMethod $logMethod
     */
    abstract public function push(string $path, string $content, LogMethod $logMethod = LogMethod::Append): void;
}
