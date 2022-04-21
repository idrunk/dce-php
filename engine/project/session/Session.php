<?php
/**
 * Author: Drunk
 * Date: 2020-04-13 10:20
 */

namespace dce\project\session;

use dce\Dce;
use dce\project\request\Request;
use dce\storage\redis\RedisProxy;
use Swoole\Http\Request as SwooleRequest;

abstract class Session {
    private const DEFAULT_ID_NAME = 'dcesid';

    private const HEADER_SID_KEY = 'x-session-id';

    protected static string $sidName;

    protected static array $config;

    /** @var int renew时旧会话存活时间（若客户端未获取新sid仍以旧的请求，则将自动续期，否则若生成新sid后未新发请求，则旧会话会在此时间后自动过期，以兼容长连接模式） */
    protected static int $oldTtl = 180;

    protected bool $touched = false;

    private string $sid;

    /**
     * 根据Request开启Session
     * @param Request $request
     * @return static
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
            self::$config['class'] = RedisProxy::isAvailable() ? '\dce\project\session\SessionRedis' : '\dce\project\session\SessionFile';
        }
    }

    /**
     * 从Dce Request或者Swoole Http Request对象取session id（支持从header/cookie/url参数获取，客户端分别以'x-session-id/dcesid/(dcesid)'的形式为键传递参数）
     * @param Request|SwooleRequest $request
     * @return string|null
     */
    public static function getSid(Request|SwooleRequest $request): string|null {
        ($sid = ($request instanceof Request
            ? ($request->rawRequest->getHeader(self::HEADER_SID_KEY) ?: $request->cookie->get(self::getSidName()))
            : $request->cookie[self::getSidName()] ?? null
        ) ?: $request->get['(' .self::getSidName(). ')'] ?? null) && $sid = trim($sid);
        return $sid;
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
        $this->sid = $sid;
        return $this;
    }

    /**
     * 取SID
     * @param bool $withPrefix
     * @return string|null
     */
    public function getId(bool $withPrefix = false): string|null {
        if (! ($this->sid ?? 0)) return null;
        return ($withPrefix ? self::getSidName() . ':' : '') . $this->sid;
    }

    /**
     * 开启Session并初始化
     * @param Request $request
     */
    protected function open(Request $request): void {
        if (! $this->getId()) {
            $id = self::getSid($request);
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
        if (false === (self::$config['valid'])($this)) {
            $this->destroy(); // 如果session非法校验失败（可能非法异地登录等），则将其删掉
        } else {
            $this->touch($param1);
        }
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

    /**
     * 重建一个session实体并删除旧实体，更新当前对象
     * @param bool $longLive 是否长存session
     * @return $this
     */
    abstract public function renew(bool $longLive = false): static;

    /**
     * 取源信息
     * @return array{create_time: int, long_live: bool, ttl: int, expiry: int, reference: string}
     */
    abstract public function getMeta(): array;
}
