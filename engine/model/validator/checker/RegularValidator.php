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
     * @param string|int|float|null|false $value
     * @return ValidatorException|null
     */
    protected function check(string|int|float|null|false $value):ValidatorException|null {
        $regexp = $this->getProperty('regexp', '{{label}}未配置表达式');
        if ($regexp) {
            if (! Char::isRegexp($regexp)) {
                $this->addError('{{regexp}} 不是有效正则表达式');
            } else if (! preg_match($regexp->value, $value)) {
                $this->addError($this->getGeneralError($regexp->error, '{{label}}输入不正确'));
            }
        }

        return $this->getError();
    }
}