<?php
/**
 * Author: Drunk
 * Date: 2020-04-13 10:56
 */

namespace dce\project\request;

abstract class Cookie {
    /**
     * 取全部Cookie数据
     * @param string $key
     * @return array
     */
    abstract public function getAll(string $key): array;

    /**
     * 取某个Cookie值
     * @param string $key
     * @return mixed
     */
    abstract public function get(string $key): mixed;

    /**
     * 设置Cookie值
     * @param string $key
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     */
    abstract public function set(string $key, string $value = "", int $expire = 0, string $path = "", string $domain = "", bool $secure = false, bool $httpOnly = false): void;

    /**
     * 删除某个Cookie值
     * @param string $key
     */
    abstract public function delete(string $key): void;
}
