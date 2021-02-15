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
                    $this->addError($this->getGeneralError(null, '缺少必传参数{{label}}'));
                }
            } else {
                $this->addError($this->getGeneralError(null, '{{label}}不能为空'));
            }
        }
        return $this->getError();
    }
}