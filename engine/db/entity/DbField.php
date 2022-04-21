<?php
/**
 * Author: Drunk
 * Date: 2019/9/3 11:12
 */

namespace dce\db\entity;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class DbField extends Field {
    /**
     * DbField constructor.
     * @param FieldType|null $type 字段类型, 已在FieldType类定义为常量
     * @param int $length 字符串或Decimal字段最大字符数或数值字段最大字节
     * @param string|int|float|false $default 默认值
     * @param string $comment 字段注释
     * @param bool $primary 是否主键
     * @param bool $null 是否允许Null值
     * @param bool $increment 是否自增
     * @param bool $unsigned 是否无符号
     * @param int $precision 允许小数位
     */
    public function __construct(
        FieldType|null $type = null,
        int $length = 0,
        string|int|float|false $default = false,
        string $comment = '',
        bool $primary = false,
        bool $null = false,
        bool $increment = false,
        bool $unsigned = true,
        int $precision = 0,
    ) {
        false !== $default && $this->setDefault($default);
        '' !== $comment && $this->setComment($comment);
        $type && $this->setType($type, $length, $unsigned, $precision);
        $primary && $this->setPrimary();
        $null && $this->setNull();
        $increment && $this->setIncrement();
    }
}
