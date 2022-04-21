<?php
/**
 * Author: Drunk
 * Date: 2021-11-23 22:17
 */

namespace dce\sharding\parser\mysql;

use dce\sharding\parser\MysqlParser;

/**
 * @note 临时类
 */
class MysqlCompareExpressionParser extends MysqlParser {
    public MysqlParser $left;
    public string $operator;
    public MysqlParser $right;

    public const Operators = ['=', '>', '<', '>=', '<='];

    public function toArray(): array {
        return [
            'type' => 'compare_expression',
            'name' => $this->operator,
            'left' => $this->left->toArray(),
            'right' => $this->right->toArray(),
        ];
    }

    public function __toString(): string {
        return "$this->left$this->operator$this->right";
    }

    public static function build(MysqlParser $left, string $operator, MysqlParser $right): self {
        $instance = new self('');
        $instance->left = $left;
        $instance->operator = $operator;
        $instance->right = $right;
        return $instance;
    }
}