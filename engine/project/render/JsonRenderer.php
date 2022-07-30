<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/3/23 23:23
 */

namespace dce\project\render;

use dce\project\Controller;
use dce\project\request\RawRequest;
use dce\project\request\RawRequestHttp;
use stdClass;

class JsonRenderer extends Renderer {
    /** @inheritDoc */
    protected function setContentType(RawRequest $rawRequest): void {
        $rawRequest instanceof RawRequestHttp && $rawRequest->header('Content-Type', 'application/json; charset=utf-8');
    }

    /** @inheritDoc */
    protected function rendering(Controller $controller, mixed $data): string {
        return json_encode(false === $data ? ($controller->getAllAssignedStatus() ?: new stdClass) : $data, JSON_UNESCAPED_UNICODE);
    }
}