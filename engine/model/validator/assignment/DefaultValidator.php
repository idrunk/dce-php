<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 10:37
 */

namespace dce\model\validator\assignment;

use dce\model\validator\TypeAssignment;

class DefaultValidator extends TypeAssignment {
    protected string $default;

    protected function getValue(): mixed {
        $value = parent::getValue();
        // 没赋值则取默认值赋值器定义的default属性值
        return false !== $value ? $value : ($this->getProperty('default')->value ?? null);
    }
}
