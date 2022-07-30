<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/7 17:28
 */

namespace drunk\debug;

use dce\Dce;
use dce\log\FileOutput;
use dce\log\HttpOutput;
use dce\log\MatrixOutput;
use drunk\debug\output\CliDebug;
use drunk\debug\output\DebugStorable;
use drunk\debug\output\HtmlDebug;
use drunk\debug\output\LogDebug;
use Throwable;

final class Debug {
    public const END_HTML = 'html';
    public const END_CLI = 'cli';
    public const END_LOG = 'log';

    public const STORAGE_FILE = 'file';
    public const STORAGE_HTTP = 'http';

    private DebugMatrix $endInst;

    private string|null $endClass = null;

    private string $logPath;

    private MatrixOutput|null $logStorage = null;

    private bool $dumpThenDie = false;

    private function __construct() {}

    /**
     * 便捷设置输出终端
     * @param string $endName
     * @return Debug
     * @throws DebugException
     */
    public function end(string $endName): self {
        try {
            $this->endClass = match(strtolower($endName)) {
                self::END_HTML => HtmlDebug::class,
                self::END_CLI => CliDebug::class,
                self::END_LOG => LogDebug::class,
            };
        } catch (Throwable) {
            throw (new DebugException(DebugException::INVALID_END))->format($endName);
        }
        return $this;
    }

    /**
     * 获取输出终端
     * @return DebugMatrix
     */
    private function getEnd(): DebugMatrix {
        if (! $this->endClass) {
            $this->endClass = php_sapi_name() === 'cli' || self::isAjax() ? CliDebug::class : HtmlDebug::class;
        }
        if (! isset($this->endInst)) {
            $this->endInst = new $this->endClass;
        }
        return $this->endInst;
    }

    /**
     * 便捷开启日志
     * @param string $path
     * @return $this
     */
    public function log(string $path): self {
        $this->logPath = $path;
        return $this;
    }

    /**
     * 设置日志储存引擎
     * @param string|MatrixOutput|null $storage
     * @param string $root
     * @return Debug
     * @throws DebugException
     */
    public function storage(string|MatrixOutput|null $storage, string $root = ''): self {
        if (is_string($storage)) {
            if (! $root && ! $root = $storage === self::STORAGE_FILE ? Dce::$config->debug['file_root'] : Dce::$config->debug['url_root']) {
                throw new DebugException(DebugException::INVALID_STORAGE_PATH);
            }
            try {
                $storage = match ($storage) {
                    self::STORAGE_FILE => new FileOutput($root),
                    self::STORAGE_HTTP => new HttpOutput($root),
                };
            } catch (Throwable) {
                throw (new DebugException(DebugException::INVALID_STORAGE_NAME))->format($storage);
            }
        }
        $this->logStorage = $storage;
        return $this;
    }

    /**
     * 设置输出时挂起
     * @return Debug
     */
    public function die(): self {
        $this->dumpThenDie = true;
        return $this;
    }

    // 预留
    public function limit(int $limit): self {
        return $this;
    }

    /**
     * 调试点标记
     * @param mixed $_
     * @return $this
     * @throws DebugException
     */
    public function mark(mixed $_ = null): self {
        $this->getEnd()->mark();
        return $this;
    }

    /**
     * 按阶批量打印
     * @param mixed $_
     * @return $this
     * @throws DebugException
     */
    public function step(mixed $_ = null): self {
        $this->getEnd()->step();
        return $this;
    }

    /**
     * 逐步点印
     * @param mixed $_
     * @throws DebugException
     */
    public function point(mixed $_ = null): void {
        $end = $this->getEnd();
        if ($this->logStorage) {
            // 如果需要记录日志, 且非自带日志功能的类, 则实例化日志对象并记录日志
            if ($end instanceof DebugStorable) {
                $end->setStorage($this->logStorage)->setPath($this->logPath);
            }
        }
        $end->point();
    }

    /**
     * 调试内容输出
     * @param mixed ...$args
     * @throws DebugException
     */
    public function dump(mixed ... $args): void {
        if ($args) {
            $this->getEnd()->mark();
        }
        $end = $this->getEnd();
        if ($this->logStorage) {
            // 如果需要记录日志, 且非自带日志功能的类, 则实例化日志对象并记录日志
            if (! $end instanceof DebugStorable) {
                (new LogDebug())->setStorage($this->logStorage)->setPath($this->logPath)->apply($end->getData())->dump();
            } else {
                $end->setStorage($this->logStorage)->setPath($this->logPath);
            }
        }
        $end->dump();
        if ($this->dumpThenDie) {
            die;
        }
    }

    /**
     * 断点调试
     * @param mixed $_
     * @throws DebugException
     */
    public function test(mixed $_ = null): void {
        $this->die()->mark()->dump();
    }

    /**
     * 判断是否ajax
     * @return bool
     */
    private static function isAjax(): bool {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    /**
     * 获取调试器实例
     * @param string $identity
     * @return Debug
     */
    public static function get(string $identity): self {
        static $map = [];
        if (!key_exists($identity, $map)) {
            $map[$identity] = new self();
        }
        return $map[$identity];
    }

    /**
     * 获取预设调试器标识
     * @param bool $needShort
     * @return string
     */
    public static function identity(bool $needShort = false): string {
        return $needShort ? 's(`^o^)' : '(*^_^*.)3`';
    }

    /**
     * 开启快捷方法
     */
    public static function enableShortcut(): void {
        require_once __DIR__ . '/DebugShortcut.php';
    }
}
