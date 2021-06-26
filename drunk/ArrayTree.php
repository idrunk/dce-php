<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021-06-23 21:12
 */

namespace drunk;

use ArrayAccess;

final class ArrayTree extends Tree {
    public function __construct(
        private array|ArrayAccess $array
    ){}

    public function getItem(): array|ArrayAccess {
        return $this->array;
    }

    public function arrayify(bool $withWrap = true): array {
        $array = $this->array;
        $array['children'] = $this->childrenArrayify();
        return $withWrap ? $array : $array['children'];
    }
}