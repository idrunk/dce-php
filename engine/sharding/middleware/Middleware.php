<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/12/31 21:06
 */

namespace dce\sharding\middleware;

abstract class Middleware {
    public function __construct(
        protected DirectiveParser $directiveParser,
    ) {
        $this->route();
    }

    abstract protected function route(): void;
}