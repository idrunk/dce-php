<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 11:03
 */

namespace dce\model\validator\filter;

use dce\model\validator\TypeFilter;

class FilterValidator extends TypeFilter {
    protected string $keyword = ' ';

    protected string $regexp;

    protected function getValue(): string|int|float|null {
        $value = parent::getValue();
        if ($regexp = $this->getProperty('regexp')) {
            $value = preg_replace($regexp->value, '', $value);
        } else if ($keyword = $this->getProperty('keyword')) {
            $value = str_replace($keyword->value, '', $value);
        }
        return $value;
    }
}
