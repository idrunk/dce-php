<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/14 4:39
 */

namespace dce\model\validator\ending;

use dce\model\validator\TypeEnding;
use dce\model\validator\ValidatorException;

class RequiredValidator extends TypeEnding {
    protected bool $allowEmpty = false;

    protected function check(string|int|float|null|false $value):ValidatorException|null {
        if (empty($value)) {
            $allowEmpty = $this->getProperty('allowEmpty');
            if ($allowEmpty->value) {
                if (false === $value) {
                    $this->addError($this->getGeneralError(null, lang(ValidatorException::REQUIRED_MISSING)));
                }
            } else {
                $this->addError($this->getGeneralError(null, lang(ValidatorException::CANNOT_BE_EMPTY)));
            }
        }
        return $this->getError();
    }
}