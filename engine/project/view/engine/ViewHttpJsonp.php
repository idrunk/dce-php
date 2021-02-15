<?php
/**
 * Author: Drunk
 * Date: 2017-2-20 15:04
 */

namespace dce\project\view\engine;

use dce\project\view\ViewHttpApi;

abstract class ViewHttpJsonp extends ViewHttpApi {
    /** @inheritDoc */
    protected function setContentType(): void {
        @$this->httpRequest->header('Content-Type', 'application/javascript; charset=utf-8');
    }

    /** @inheritDoc */
    protected function rendering(): string {
        $callback = $this->request->get[$this->request->node->jsonpCallback];
        $response = json_encode($this->getAllAssignedStatus(), JSON_UNESCAPED_UNICODE);
        return "{$callback}({$response})";
    }
}
