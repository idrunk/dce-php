<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/2/23 13:25
 */

namespace dce\rpc;

use dce\base\Exception;
use dce\i18n\Language;
use dce\log\LogManager;
use Throwable;

// 1700-1799
class RpcException extends Exception {
    // 脚本异常
    #[Language(['rpc_connection配置异常'])]
    public const INVALID_RPC_CONNECTION_CONFIG = 1700;

    #[Language(['rpc_connection[]配置异常'])]
    public const INVALID_RPC_CONNECTION_CONFIG2 = 1701;

    #[Language(['rpc_connection[][]配置异常'])]
    public const INVALID_RPC_CONNECTION_CONFIG3 = 1702;

    #[Language(['类 %s 未注册远程过程服务'])]
    public const CLASS_NOT_REGISTER = 1703;

    #[Language(['%s 不是合法类'])]
    public const INVALID_CLASS = 1704;

    #[Language(['类名 %s 当前集与前次类名集不一致, 或者已通过名字空间设置'])]
    public const CLASS_CONFLICT = 1705;

    #[Language(['命名空间 %s 当前集与前次命名空间集不一致'])]
    public const NAMESPACE_CONFLICT = 1706;

    #[Language(['预载文件不存在'])]
    public const PRELOAD_NOT_EXISTS = 1707;

    #[Language(['预载命名空间根目录不存在'])]
    public const PREPARE_ROOT_NOT_EXISTS = 1708;

    #[Language(['定义包过长'])]
    public const PACKAGE_TOO_LONG = 1709;

    #[Language(['formatter未定义'])]
    public const FORMATTER_MISSING = 1710;

    #[Language(['formatter类型非法'])]
    public const FORMATTER_TYPE_INVALID = 1711;

    #[Language(['无效包长'])]
    public const INVALID_PACKAGE_LENGTH = 1712;

    #[Language(['RpcHosts为空'])]
    public const EMPTY_RPC_HOSTS = 1713;

    #[Language(['无效RpcHosts配置'])]
    public const INVALID_RPC_HOSTS = 1714;

    #[Language(['rpc_service配置异常'])]
    public const INVALID_RPC_SERVICE_CONFIG = 1715;

    // 运行时异常
    #[Language(['请求发送失败'])]
    public const REQUEST_FAILED = 1750;

    #[Language(['响应超时'])]
    public const RESPONSE_TIMEOUT = 1751;

    #[Language(['空响应, 可能远程服务异常导致'])]
    public const EMPTY_RESPONSE = 1752;

    #[Language(['异常跨域请求, 授权校验失败'])]
    public const NEED_NATIVE = 1753;

    #[Language(['非法跨域请求, 授权校验失败'])]
    public const NEED_LOCAL = 1754;

    #[Language(['非法请求, 授权校验失败'])]
    public const NOT_IN_WHITE_LIST = 1755;

    #[Language(['异常请求, 授权校验失败'])]
    public const VALID_FAILED = 1756;

    #[Language(['%s 非RPC类'])]
    public const NOT_RPC_CLASS = 1757;


    #[Language(['连接异常, Code: %s, Message: %s'])]
    public const INVALID_CONNECTION = 1799;


    public static function render(Throwable $throwable, bool|null $simple = false, bool $html = false): string {
        return match($throwable->getCode()) {
            self::INVALID_CONNECTION => LogManager::exceptionRender($throwable, warn: true), // 对于异常断开，警告即可
            default => parent::render($throwable),
        };
    }
}
