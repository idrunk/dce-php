<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/14 4:32
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;

class UrlValidator extends TypeChecker {
    /**
     * @param string|int|float|null|false $value
     * @return ValidatorException|null
     */
    protected function check(string|int|float|null|false $value):ValidatorException|null {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($this->getGeneralError(null, '{{label}}非有效Url地址'));
        }

        return $this->getError();
    }
}