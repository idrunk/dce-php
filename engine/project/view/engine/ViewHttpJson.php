<?php
/**
 * Author: Drunk
 * Date: 2017-2-20 15:04
 */

namespace dce\project\view\engine;

use dce\project\view\ViewHttpApi;

abstract class ViewHttpJson extends ViewHttpApi {
    /** @inheritDoc */
    protected function setContentType(): void {
        @$this->httpRequest->header('Content-Type', 'application/json; charset=utf-8');
    }

    /** @inheritDoc */
    protected function rendering(): string {
        return json_encode($this->getAllAssignedStatus(), JSON_UNESCAPED_UNICODE);
    }
}
