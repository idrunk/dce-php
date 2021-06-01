<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/3/23 23:23
 */

namespace dce\project\render;

use dce\project\Controller;
use dce\project\request\RawRequest;
use dce\project\request\RawRequestHttp;

class JsonpRenderer extends Renderer {
    /** @inheritDoc */
    protected function setContentType(RawRequest $rawRequest): void {
        $rawRequest instanceof RawRequestHttp && $rawRequest->header('Content-Type', 'application/javascript; charset=utf-8');
    }

    /** @inheritDoc */
    protected function rendering(Controller $controller, mixed $data): string {
        $callback = $controller->request->get[$controller->request->node->jsonpCallback];
        $response = json_encode(false === $data ? $controller->getAllAssignedStatus() : $data, JSON_UNESCAPED_UNICODE);
        return "{$callback}({$response})";
    }
}