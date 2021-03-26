<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-03-11 11:30
 */

namespace dce\controller;

use dce\Dce;
use dce\project\Controller;

class UtilityController extends Controller {
    // 清除缓存
    public function cacheClear() {
        $cacheType = $this->request->cli['--type'] ?? $this->request->cli['-t'] ?? 'file';
        Dce::$cache->{$cacheType}->clear();
        $this->print('缓存清除完毕');
    }
}