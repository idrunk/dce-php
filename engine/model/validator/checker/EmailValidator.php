<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 12:01
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;

class EmailValidator extends TypeChecker {
    /**
     * @param mixed $value
     * @return ValidatorException|null
     */
    protected function check(mixed $value): ValidatorException|null {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($this->getGeneralError(null, lang(ValidatorException::INVALID_EMAIL)));
        }

        return $this->getError();
    }
}
