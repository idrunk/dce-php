<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/12 20:35
 */

namespace dce\project\request;

use dce\base\Exception;
use dce\i18n\Language;

// 830-859
class RequestException extends Exception {
    // 运行时错误
    #[Language(['节点 %s 未配置控制器或配置错误'])]
    public const NODE_NO_CONTROLLER = 830;

    #[Language(['控制器 %s 异常或不存在, 需继承Controller子类'])]
    public const NODE_CONTROLLER_INVALID = 831;

    #[Language(['控制器方法 %s 不存在'])]
    public const CONTROLLER_METHOD_INVALID = 832;

    #[Language(['未安装Swoole扩展, 无法开启协程'])]
    public const COROUTINE_NEED_SWOOLE = 833;

    #[Language(['%s 页面不存在'])]
    public const PATH_WAS_BLOCKED = 834;

    #[Language(['%s 节点不存在'])]
    public const NODE_LOCATION_FAILED = 835;

    #[Language(['Url不合法'])]
    public const INVALID_URL = 840;

    #[Language(['无法解析Url'])]
    public const CANNOT_PARSE_URL = 841;

}