<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021-06-25 23:15
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;

class BitsetValidator extends TypeChecker {
    protected array $set;

    /** @inheritDoc */
    protected function check(float|false|int|string|null $value): ValidatorException|null {
        $set = $this->getProperty('set', lang(ValidatorException::SET_REQUIRED));
        if ($set && ($bits = array_reduce($set->value, fn($tb, $b) => $tb | $b, 0)) && (($value | $bits) !== $bits)) {
            $this->addError($this->getGeneralError($set->error, lang(ValidatorException::VALUE_NOT_IN_BITSET)));
        }

        return $this->getError();
    }
}