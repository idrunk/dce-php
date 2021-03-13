<?php
/**
 * Author: Drunk
 * Date: 2020-04-13 10:20
 */

namespace dce\project\request;

use dce\Dce;
use dce\storage\redis\DceRedis;

abstract class Session {
    private const DEFAULT_ID_NAME = 'dcesid';

    protected static string $sidName;

    protected static array $config;

    private string $sid;

    private bool $touched = false;

    /**
     * 根据Request开启Session
     * @param Request $request
     * @return static
     * @throws SessionException
     */
    public static function newByRequest(Request $request): static {
        self::initConfig();
        /** @var static $instance */
        $instance = new self::$config['class']();
        if (self::$config['auto_open'] ?? 0) {
            $instance->open($request);
        }
        return $instance;
    }

    /**
     * 在长连接建立时生成一个Session实例, 或在外部实例化Session并设置好Sid, 如在Session管理器等外部中操作Session
     * @param string|true $sid
     * @return static
     */
    public static function newBySid(string|bool $sid): static {
        self::initConfig();
        return (new self::$config['class']())->setId(true === $sid ? self::genId() : $sid);
    }

    /** 初始化处理Session配置 */
    private static function initConfig(): void {
        if (isset(self::$config)) {
            return;
        }
        self::$config = Dce::$config->session;
        if (! self::$config['class']) {
            self::$config['class'] = DceRedis::isAvailable() ? '\dce\project\request\SessionRedis' : '\dce\project\request\SessionFile';
        }
    }

    /**
     * 取Session Id键名
     * @return string
     */
    public static function getSidName(): string {
        if (! isset(self::$sidName)) {
            self::$sidName = Dce::$config->session['name'] ?: self::DEFAULT_ID_NAME;
        }
        return self::$sidName;
    }

    /**
     * 直接设置SID
     * @param string $sid
     * @return $this
     */
    final protected function setId(string $sid): static {
        if (isset($this->sid)) {
            throw new SessionException('Session实例仅能绑定一个sid');
        }
        $this->sid = $sid;
        return $this;
    }

    /**
     * 取SID
     * @param bool $withPrefix
     * @return string|null
     */
    public function getId(bool $withPrefix = false): string|null {
        if (! isset($this->sid)) {
            return null;
        }
        return ($withPrefix ? self::getSidName() . ':' : '') . $this->sid;
    }

    /**
     * 开启Session并初始化
     * @param Request $request
     * @throws SessionException
     */
    protected function open(Request $request): void {
        if (! $this->getId()) {
            $id = $request->cookie->get(self::getSidName());
            if (! $id) {
                $id = self::genId();
                $request->cookie->set(self::getSidName(), $id);
            }
            $this->setId($id);
        }
    }

    /**
     * 生成sid
     * @return string
     */
    protected static function genId(): string {
        return sha1(uniqid('', true));
    }

    /**
     * 尝试刷新Session, 一个实例仅刷新一次, 优化性能
     * @param mixed|null $param1 附加参数, 供子类传参
     */
    protected function tryTouch(mixed $param1 = null): void {
        if ($this->touched) {
            return;
        }
        $this->touch($param1);
        $this->touched = true;
    }

    /**
     * 判断Session是否存活
     * @return bool
     */
    abstract public function isAlive(): bool;

    /**
     * 设置Session值
     * @param string $key
     * @param mixed $value
     */
    abstract public function set(string $key, mixed $value): void;

    /**
     * 取某个Session值
     * @param string $key
     * @return mixed
     */
    abstract public function get(string $key): mixed;

    /**
     * 取全部Session数据
     * @return array
     */
    abstract public function getAll(): array;

    /**
     * 删除某个Session值
     * @param string $key
     */
    abstract public function delete(string $key): void;

    /** 销毁Session */
    abstract public function destroy(): void;

    /**
     * 更新最后接触时间, 给Session续命
     * @param mixed|null $param1 附加参数, 供子类传参
     */
    abstract protected function touch(mixed $param1 = null): void;
}
