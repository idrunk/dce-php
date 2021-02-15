<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/5/4 17:02
 */

namespace dce\project\request;

use dce\base\TraitModel;

class SessionForm {
    use TraitModel;

    public int $id;

    public string $sid;

    public string $mid;

    public int $fd;

    public string $apiHost;

    public int $apiPort;

    public string $extra;

    public string $createTime;

    public function __construct(array $properties) {
        $this->setProperties($properties);
    }
}