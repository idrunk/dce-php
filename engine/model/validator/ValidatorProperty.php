<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/9/21 2:46
 */

namespace dce\model\validator;

use drunk\Utility;

class ValidatorProperty {
    /** @var string|int|float|array 校验规则属性值 */
    public string|int|float|array $value;

    public string|null $error = null;

    public function __construct(string $name, string|int|float|array $value, bool $isArrayType, string|null $modelPropertyLabel) {
        if ($isArrayType) {
            if (is_array($value)) {
                if (! is_array($value[0] ?? null) || ! is_string($value[1] ?? '')) {
                    $value = [$value];
                }
            } else if (null !== $value) {
                throw new ValidatorException("{$modelPropertyLabel}字段的校验器属性{$name}必须为数组");
            }
        } else if (! is_array($value)) {
            $value = [$value];
        }
        $this->value = $value[0];
        if (isset($value[1])) {
            $this->error = $value[1];
        }
    }

    public function __toString(): string {
        return Utility::printable($this->value);
    }
}