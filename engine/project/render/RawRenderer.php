<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/6/3 23:33
 */

namespace dce\project\render;

use dce\project\Controller;
use dce\project\request\RawRequest;

class RawRenderer extends Renderer {
    /** @inheritDoc */
    protected function setContentType(RawRequest $rawRequest): void {}

    /** @inheritDoc */
    protected function rendering(Controller $controller, mixed $data): mixed {
        return $data;
    }
}