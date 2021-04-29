<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/21 2:46
 */

namespace dce\model\validator;

use dce\i18n\Language;
use drunk\Utility;
use Stringable;

class ValidatorProperty {
    /** @var string|int|float|array 校验规则属性值 */
    public string|int|float|array $value;

    public string|Stringable|null $error = null;

    public function __construct(string $name, string|int|float|array $value, bool $isArrayType, string|null $modelPropertyLabel) {
        if ($isArrayType) {
            if (is_array($value)) {
                if (! is_array($value[0] ?? null) || ! is_string($value[1] ?? '')) {
                    $value = [$value];
                }
            } else if (null !== $value) {
                throw (new ValidatorException(ValidatorException::VALIDATOR_PROPERTY_MUST_BE_ARRAY))->format($modelPropertyLabel, $name);
            }
        } else if (! is_array($value)) {
            $value = [$value];
        }
        $this->value = $value[0];
        if (isset($value[1])) {
            $errCode = $value[2] ?? 0;
            // 如果配置了多语种映射或者异常码, 则实例化为Language, 否则为string
            $this->error = is_array($value[1]) || $errCode ? new Language($value[1], $errCode) : $value[1];
        }
    }

    public function __toString(): string {
        return Utility::printable($this->value);
    }
}