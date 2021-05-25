<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/12 9:12
 */

namespace dce\project\request;

use dce\base\Exception;
use dce\i18n\Language;
use dce\loader\StaticInstance;

// 1220-1229
class RouterException extends Exception {
    protected static array $http = [404];

    #[Language(['节点匹配规则配置错误'])]
    public const NODE_CONFIG_ERROR = 1220;

    #[Language(['项目定位失败，请检查是否未设置默认项目或节点配置错误'])]
    public const PROJECT_NO_CHILD = 1221;

    #[Language(['项目定位失败，请检查是否未设置默认项目或节点配置错误'])]
    public const PROJECT_LOCATION_FAILED = 1222;

    #[Language(['节点定位失败，请检查url是否正确'])]
    public const NODE_LOCATION_FAILED = 404;
}