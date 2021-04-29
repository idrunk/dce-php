<?php
/**
 * Author: Drunk
 * Date: 2020-04-15 14:06
 */

namespace dce\project\node;

use dce\base\Exception;
use dce\i18n\Language;

// 800-819
class NodeException extends Exception {
    #[Language(['节点配置缺少path属性'])]
    public const NODE_PATH_MISSION = 800;

    #[Language(['Methods必须为字符串数组, 如["get", "post"]'])]
    public const NODE_METHODS_NEED_ARRAY = 801;
}
