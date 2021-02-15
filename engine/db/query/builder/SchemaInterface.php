<?php
/**
 * Author: Drunk
 * Date: 2019/7/29 10:46
 */

namespace dce\db\query\builder;

interface SchemaInterface {
    public function getConditions(): array;

    public function getParams(): array;

    public function __toString(): string;
}
