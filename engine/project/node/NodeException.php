<?php
/**
 * Author: Drunk
 * Date: 2020-04-15 14:06
 */

namespace dce\project\node;

use dce\base\Exception;
use dce\i18n\Language;

// 1210-1219
class NodeException extends Exception {
    #[Language(['节点配置 %s 缺少path属性'])]
    public const NODE_PATH_MISSION = 1210;

    #[Language(['Methods必须为字符串数组, 如["get", "post"]'])]
    public const NODE_METHODS_NEED_ARRAY = 1211;
}
