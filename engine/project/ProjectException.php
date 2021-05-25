<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/5/1 17:47
 */

namespace dce\project;

use dce\base\Exception;
use dce\i18n\Language;

// 1200-1209
class ProjectException extends Exception {
    // 脚本异常
    #[Language(['项目目录 %s 无效'])]
    public const PROJECT_PATH_INVALID = 1200;
}