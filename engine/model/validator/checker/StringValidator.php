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
     * @param string|int|float|null|false $value
     * @return ValidatorException|null
     */
    protected function check(string|int|float|null|false $value):ValidatorException|null {
        if (! is_string($value)) {
            $this->addError($this->getGeneralError(null, '{{label}}非有效字符'));
        } else {
            $min = $this->getProperty('min');
            $max = $this->getProperty('max');
            if ($min || $max) {
                $length = mb_strlen($value);

                if ($min && $length < $min->value) {
                    $this->addError($this->getGeneralError($min->error, '{{label}}不能小于{{min}}个字符'));
                }

                if ($max && $length > $max->value) {
                    $this->addError($this->getGeneralError($max->error, '{{label}}不能多于{{max}}个字符'));
                }
            }
        }

        return $this->getError();
    }
}