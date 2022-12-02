<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/14 3:05
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;
use drunk\Char;

class RegularValidator extends TypeChecker {
    protected string $regexp;

    /**
     * @param mixed $value
     * @return ValidatorException|null
     */
    protected function check(mixed $value):ValidatorException|null {
        $regexp = $this->getProperty('regexp', lang(ValidatorException::REGEXP_REQUIRED));
        if ($regexp) {
            if (! Char::isRegexp($regexp->value)) {
                $this->addError(lang(ValidatorException::INVALID_REGEXP));
            } else if (! preg_match($regexp->value, $value)) {
                $this->addError($this->getGeneralError($regexp->error, lang(ValidatorException::INVALID_INPUT)));
            }
        }

        return $this->getError();
    }
}