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
     * @param string|int|float|null|false $value
     * @return ValidatorException|null
     */
    protected function check(string|int|float|null|false $value):ValidatorException|null {
        if (! is_numeric($value)) {
            $this->addError($this->getGeneralError(null, '{{label}}非有效数字'));
        } else {
            $negative = $this->getProperty('negative');
            if (! ($negative->value ?? null) && $value < 0) {
                $this->addError($this->getGeneralError($negative->error ?? null, '{{label}}不能为负数'));
            }

            $decimal = $this->getProperty('decimal');
            if (! ($decimal->value ?? null) && ! ctype_digit((string) $value)) {
                $this->addError($this->getGeneralError($decimal->error ?? null, '{{label}}不能为小数'));
            }

            $min = $this->getProperty('min');
            if ($min && $value < $min->value) {
                $this->addError($this->getGeneralError($min->error, '{{label}}不能小于{{min}}'));
            }

            $max = $this->getProperty('max');
            if ($max && $value > $max->value) {
                $this->addError($this->getGeneralError($max->error, '{{label}}不能大于{{max}}'));
            }
        }

        return $this->getError();
    }
}
