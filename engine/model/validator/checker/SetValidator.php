<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/14 1:56
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;

/**
 * Class SetValidator
 * @package dce\model\validator\library\checker
 */
class SetValidator extends TypeChecker {
    protected array $set;

    /**
     * @param string|int|float|null|false $value
     * @return ValidatorException|null
     */
    protected function check(string|int|float|null|false $value):ValidatorException|null {
        $set = $this->getProperty('set', '{{label}}校验器未配置set属性');
        if ($set && ! in_array($value, $set->value)) {
            $this->addError($this->getGeneralError($set->error, '{{label}}值{{value}}必须为{{set}}中的一个'));
        }

        return $this->getError();
    }
}