<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/14 3:55
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;

class StringValidator extends TypeChecker {
    protected int $min;

    protected int $max;

    /**
     * @param mixed $value
     * @return ValidatorException|null
     */
    protected function check(mixed $value):ValidatorException|null {
        if (! is_string($value)) {
            $this->addError($this->getGeneralError(null, lang(ValidatorException::INVALID_STRING)));
        } else {
            $min = $this->getProperty('min');
            $max = $this->getProperty('max');
            if ($min || $max) {
                $length = mb_strlen($value);

                if ($min && $length < $min->value) {
                    $this->addError($this->getGeneralError($min->error, lang(ValidatorException::CANNOT_LESS_THAN)));
                }

                if ($max && $length > $max->value) {
                    $this->addError($this->getGeneralError($max->error, lang(ValidatorException::CANNOT_MORE_THAN)));
                }
            }
        }

        return $this->getError();
    }
}