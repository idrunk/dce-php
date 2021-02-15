<?php
/**
 * Author: Drunk
 * Date: 2020-04-13 10:20
 */

namespace dce\project\request;

use dce\Dce;

abstract class Session {
    private const DEFAULT_ID_NAME = 'dcesid';

    protected static string $sidName;

    private string $sid;

    /**
     * 根据Request开启Session
     * @param Request $request
     * @return static
     */
    public static function inst(Request $request): static {
        $instance = new $request->config->session['class']($request);
        if ($request->config->session['auto_start'] ?: 0) {
            $instance->open($request);
        }
        return $instance;
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
    public function setId(string $sid): self {
        $this->sid = $sid;
        return $this;
    }

    /**
     * 取SID
     * @param bool $withPrefix
     * @return string|null
     */
    public function getId(bool $withPrefix = true): string|null {
        if (! isset($this->sid)) {
            return null;
        }
        return ($withPrefix ? Dce::getId() . ':' . self::getSidName() . ':' : '') . $this->sid;
    }

    /**
     * 开启Session并初始化
     * @param Request $request
     */
    protected function openInit(Request $request): void {
        if (! $this->getId(false)) {
            $id = $request->cookie->get(self::getSidName());
            if (! $id) {
                $id = sha1(uniqid('', true));
                $request->cookie->set(self::getSidName(), $id);
            }
            $this->setId($id);
        }
    }

    /**
     * 开启Session
     * @param Request $request
     */
    abstract public function open(Request $request): void;

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

    /**
     * 销毁Session
     */
    abstract public function destroy(): void;
}
