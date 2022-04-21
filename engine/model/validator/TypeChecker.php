<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 10:36
 */

namespace dce\model\validator;

abstract class TypeChecker extends ValidatorAbstract {
    protected function valid(): bool {
        $value = $this->getValue();
        if (! empty($value)) {
            $result = $this->check($value);
            if ($result instanceof ValidatorException) {
                throw $result;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     * @return ValidatorException|null
     */
    abstract protected function check(mixed $value):? ValidatorException;
}
