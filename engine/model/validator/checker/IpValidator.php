<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 17:23
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;

class IpValidator extends TypeChecker {
    protected bool $isIp4 = true;

    /**
     * @param string|int|float|null|false $value
     * @return ValidatorException|null
     */
    protected function check(string|int|float|null|false $value):ValidatorException|null {
        if (! filter_var($value, FILTER_VALIDATE_IP, $this->getProperty('isIp4')->value ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6)) {
            $this->addError($this->getGeneralError(null, lang(ValidatorException::INVALID_IP)));
        }

        return $this->getError();
    }
}
