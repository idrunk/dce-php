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
    public const TYPE_KEY_EXISTS = 1;

    protected array $set;

    protected int $type;

    /**
     * @param mixed $value
     * @return ValidatorException|null
     */
    protected function check(mixed $value):ValidatorException|null {
        $set = $this->getProperty('set', lang(ValidatorException::SET_REQUIRED));
        $type = $this->getProperty('type');
        if ($set && ! (($type->value ?? 0) & self::TYPE_KEY_EXISTS ? key_exists($value, $set->value) : in_array($value, $set->value))) {
            $this->addError($this->getGeneralError($set->error, lang(ValidatorException::VALUE_NOT_IN_SET)));
        }

        return $this->getError();
    }
}