<?php
/**
 * Author: Drunk
 * Date: 2019-12-20 15:16
 */

namespace dce\sharding\parser\mysql;

use dce\sharding\parser\MysqlParser;
use dce\sharding\parser\StatementParserException;

class MysqlValueParser extends MysqlParser {
    public string|float|null $value = null;

    public bool $isNumeric = false;

    private static string $valueQuotes = '\'';

    private function detect(string $operator): bool {
        return $operator === self::$valueQuotes;
    }

    private function detectByWord(string $word): bool {
        return is_numeric($word);
    }

    private function parse(string|float|null $value): void {
        $this->value = $value;
    }

    public function toArray(): array {
        return [
            'type' => 'value',
            'value' => $this->value,
        ];
    }

    public function __toString(): string {
        $value = $this->isNumeric ? $this->value : "'{$this->value}'";
        return $value;
    }

    public static function build(string $statement, int|null & $offset, string $operator): self|null {
        $instance = new self($statement, $offset);
        if ($instance->detect($operator)) {
            // 解析引号字符串
            $string = $instance->parseString($operator);
            $instance->parse($string);
        } else if (self::isMinus($operator)) {
            // 解析负数
            $value = $instance->preParseWord();
            if (! is_numeric($value)) {
                throw new StatementParserException('负号后跟的不是有效数字');
            }
            $instance->isNumeric = true;
            $instance->parse(- $value);
        } else {
            return null;
        }
        return $instance;
    }

    public static function buildByWord(string $statement, int|null & $offset, string $value): self|null {
        $instance = new self($statement, $offset);
        if ($instance->detectByWord($value)) {
            $instance->parse(+ $value);
            $instance->isNumeric = true;
            return $instance;
        }
        return null;
    }
}
