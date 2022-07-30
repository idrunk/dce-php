<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 17:31
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;

class NumberValidator extends TypeChecker {
    /** @var bool 是否允许小数 */
    protected bool $decimal = true;

    /** @var bool 是否允许负数 */
    protected bool $negative = true;

    protected float $max;

    protected float $min;

    /**
     * @param mixed $value
     * @return ValidatorException|null
     */
    protected function check(mixed $value):ValidatorException|null {
        if (! is_numeric($value)) {
            $this->addError($this->getGeneralError(null, lang(ValidatorException::INVALID_NUMBER)));
        } else {
            $negative = $this->getProperty('negative');
            if (! ($negative->value ?? null) && $value < 0) {
                $this->addError($this->getGeneralError($negative->error ?? null, lang(ValidatorException::CANNOT_BE_NEGATIVE)));
            }

            $decimal = $this->getProperty('decimal');
            if (! ($decimal->value ?? null) && ! ctype_digit(ltrim((string) $value, '-'))) {
                $this->addError($this->getGeneralError($decimal->error ?? null, lang(ValidatorException::CANNOT_BE_FLOAT)));
            }

            $min = $this->getProperty('min');
            if ($min && $value < $min->value) {
                $this->addError($this->getGeneralError($min->error, lang(ValidatorException::CANNOT_SMALL_THAN)));
            }

            $max = $this->getProperty('max');
            if ($max && $value > $max->value) {
                $this->addError($this->getGeneralError($max->error, lang(ValidatorException::CANNOT_LARGE_THAN)));
            }
        }

        return $this->getError();
    }
}
