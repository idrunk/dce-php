<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/10/27 22:26
 */

namespace dce\i18n;

use dce\loader\Decorator;

// todo 暂未想好
class TextMapping implements Decorator {
    public static Language|array $prepare = ['正在加载Dce类库 ...', 'DCE library loading ...'];
}