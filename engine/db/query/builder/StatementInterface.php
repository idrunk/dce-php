<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/8/4 11:26
 */

namespace dce\db\query\builder;

interface StatementInterface {
    public function getParams(): array;

    public function __toString(): string;
}
