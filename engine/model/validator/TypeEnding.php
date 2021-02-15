<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 10:36
 */

namespace dce\model\validator;

abstract class TypeEnding extends TypeChecker {
    protected function valid(): bool {
        $result = $this->check($this->getValue());
        if ($result instanceof ValidatorException) {
            throw $result;
        }
        return true;
    }
}
