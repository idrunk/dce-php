<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 11:14
 */

namespace dce\model\validator\checker;

use dce\model\validator\TypeChecker;
use dce\model\validator\ValidatorException;

class DateValidator extends TypeChecker {
    public const TYPE_MONTH = 'month';

    public const TYPE_DATE = 'date';

    public const TYPE_TIME = 'time';

    public const TYPE_DATETIME = 'datetime';

    protected string $type = self::TYPE_DATE;

    protected array $formatSet;

    protected string $max;

    protected string $min;

    private static array $defaultFormatMap = [
        self::TYPE_MONTH => ['Y-m'],
        self::TYPE_DATE => ['Y-m-d'],
        self::TYPE_TIME => ['H:i:s', 'H:i'],
        self::TYPE_DATETIME => ['Y-m-d H:i:s', 'Y-m-d H:i'],
    ];

    /**
     * @param string|int|float|null|false $value
     * @return ValidatorException|null
     */
    protected function check(string|int|float|null|false $value):ValidatorException|null {
        $formatSet = $this->getProperty('formatSet');
        $type = $this->getProperty('type', lang(ValidatorException::FORMATSET_OR_TYPE_REQUIRED));
        if ($formatSet) {
            $formatSetValue = $formatSet->value;
        } else {
            $formatSetValue = $type ? (self::$defaultFormatMap[$type->value] ?? null): null;
        }
        if (null === $formatSetValue) {
            $this->addError(lang(ValidatorException::TYPE_REQUIRED));
        } else if (! self::validDate($formatSetValue, $value)) {
            $this->addError($this->getGeneralError($formatSet->error ?? $type->error ?? null, lang(ValidatorException::FORMAT_INVALID)));
        } else {
            $max = $this->getProperty('max');
            if ($max && strtotime($value) > strtotime($max->value)) {
                $this->addError($this->getGeneralError($max->error, lang(ValidatorException::CANNOT_LATE_THAN)));
            }
            $min = $this->getProperty('min');
            if ($min && strtotime($min->value) > strtotime($value)) {
                $this->addError($this->getGeneralError($min->error, lang(ValidatorException::CANNOT_EARLIER_THAN)));
            }
        }

        return $this->getError();
    }

    /**
     * @param array $formatSet
     * @param string $value
     * @return bool
     */
    private static function validDate(array $formatSet, string $value): bool {
        $strTime = strtotime($value);
        foreach ($formatSet as $format) {
            if (date($format, $strTime) === $value) {
                return true;
            }
        }
        return false;
    }
}
