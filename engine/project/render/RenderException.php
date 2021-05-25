<?php
/**
 * Author: Drunk
 * Date: 2020-04-13 12:51
 */

namespace dce\project\render;

use dce\base\Exception;
use dce\i18n\Language;

// 1280-1299
class RenderException extends Exception {
    #[Language(['渲染器必须继承 %s'])]
    public const RENDERER_EXTENDS_ERROR = 1280;

    #[Language(['模版文件 %s 不存在'])]
    public const TEMPLATE_NOTFOUND = 1281;

    #[Language(['布局文件 %s 不存在'])]
    public const LAYOUT_NOTFOUND = 1282;
}
