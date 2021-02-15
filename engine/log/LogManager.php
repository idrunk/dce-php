<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-04 12:11
 */

namespace dce\log;

use dce\db\connector\ScriptLogger;
use dce\db\connector\ScriptLoggerConsole;
use dce\Dce;

final class LogManager {
    public static function init() {
        if (Dce::$config->log['db']['console']) {
            ScriptLogger::addDriver(new ScriptLoggerConsole());
        }
    }
}