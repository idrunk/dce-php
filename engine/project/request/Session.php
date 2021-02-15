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
     * @return string|null
     */
    public function getId(): string|null {
        return $this->sid ?? null;
    }

    /**
     * 根据Request开启Session
     * @param Request $request
     */
    public function openByRequest(Request $request): void {
        if ($request->config->session['auto_start'] ?: 0) {
            $this->open($request);
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
