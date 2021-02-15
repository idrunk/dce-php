<?php
/**
 * Author: Drunk
 * Date: 2020-05-08 11:46
 */

namespace dce\rpc;

use dce\base\TraitModel;

final class RpcHost {
    use TraitModel;

    /** @var bool 是否unix sock */
    public bool $isUnixSock = false;

    /** @var string 主机地址 */
    public string $host;

    /** @var int 端口地址 */
    public int $port = 0;

    /** @var bool 是否需要本机访问 */
    public bool $needNative = false;

    /** @var bool 是否需要本域访问 */
    public bool $needLocal = true;

    /** @var array 允许的Ip白名单 */
    public array $ipWhiteList = [];

    /** @var string 服务密码 */
    public string $password = '';

    public function __construct(array $properties = []) {
        if (($properties['port'] ?? 0) < 1) {
            // 若未定义端口, 则视为Unix Sock, 并自动为host补前缀
            $properties['is_unix_sock'] = true;
        }
        $this->setProperties($properties);
    }

    /**
     * 设置鉴权方案
     * @param string $password
     * @param array $ipWhiteList
     * @param bool $needNative
     * @param bool $needLocal
     * @return $this
     */
    public function setAuth(string $password, array $ipWhiteList = [], bool $needNative = false, bool $needLocal = false): self {
        $this->needNative = $needNative;
        $this->needLocal = $needLocal;
        $this->ipWhiteList = $ipWhiteList;
        $this->password = $password;
        return $this;
    }
}
