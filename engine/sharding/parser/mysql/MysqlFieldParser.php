<?php
/**
 * Author: Drunk
 * Date: 2019-12-20 15:19
 */

namespace dce\sharding\parser\mysql;

use dce\sharding\parser\MysqlParser;
use dce\sharding\parser\StatementParserException;

class MysqlFieldParser extends MysqlParser {
    public string|null $field = null;

    public string|null $table = null;

    public string|null $db = null;

    /** @var string 属性连接符 */
    private static string $attrSeparators = '.';

    private function detect(string $operator): bool {
        return in_array($operator, self::$nameQuotes);
    }

    private function parse(string $field): void {
        ['field' => $this->field, 'table' => $this->table, 'db' => $this->db, ] = $this->preParse($field);
    }

    /**
     * 预解析字段, 若字段有数据库/表前缀, 也一起解析提取出来
     * @param string $part1
     * @return array
     * @throws StatementParserException
     */
    private function preParse(string $part1): array {
        $frontIsConnector = false;
        $parts = [['value' => $part1, 'wrapper' => null]];

        $this->traverse(
            function ($operator) use (& $frontIsConnector, & $parts) {
                if ($operator === self::$attrSeparators) {
                    // mark 这里有个bug, 就是如果前面有connector符了的话这里也不报错, 但是没关系, 我们不解决它,
                    // 因为错误的sql无法被执行, 也就进不到这一步, 而这里要尽量把语句解析出来, 尽量的方便即可
                    $frontIsConnector = true;
                } else if ($frontIsConnector && in_array($operator, self::$columnWildcards)) {
                    $parts[] = ['value' => $operator, 'wrapper' => null];
                    $frontIsConnector = false;
                } else if ($frontIsConnector && in_array($operator, self::$nameQuotes)) {
                    $string = $this->parseString($operator);
                    $parts[] = ['value' => $string, 'wrapper' => $operator];
                    $frontIsConnector = false;
                } else if (! $frontIsConnector || ! in_array($operator, self::$partSeparators)) {
                    $this->offset -= mb_strlen($operator);
                    return self::TRAVERSE_CALLBACK_BREAK;
                }
                return self::TRAVERSE_CALLBACK_STEP;
            },
            function ($word) use (& $frontIsConnector, & $parts) {
                if ($frontIsConnector) {
                    $parts[] = ['value' => $word];
                    $frontIsConnector = false;
                    return self::TRAVERSE_CALLBACK_STEP;
                } else {
                    $this->offset -= mb_strlen($word);
                    return self::TRAVERSE_CALLBACK_BREAK;
                }
            }
        );

        $partsCount = count($parts);
        if ($partsCount > 3) {
            $partsString = implode('.', array_column($parts, 'value'));
            throw (new StatementParserException(StatementParserException::INVALID_COLUMN))->format($partsString);
        }

        $parts = array_reverse($parts);
        $field = ['db'=>null, 'table'=>null, 'field'=>null, ];
        if ($partsCount > 2) {
            $field['db'] = $parts[2]['value'];
        }
        if ($partsCount > 1) {
            $field['table'] = $parts[1]['value'];
        }
        $field['field'] = $parts[0]['value'];

        return $field;
    }

    public function toArray(): array {
        return [
            'type' => 'field',
            'field' => $this->field,
            'table' => $this->table,
            'db' => $this->db,
        ];
    }

    public function __toString(): string {
        $field = in_array($this->field, self::$columnWildcards) ? $this->field : "`{$this->field}`";
        if ($this->table) {
            $field = "`{$this->table}`" .self::$attrSeparators. $field;
            if ($this->db) {
                $field = "`{$this->db}`" .self::$attrSeparators. $field;
            }
        }
        return $field;
    }

    public function getSelectColumnName(): string {
        return $this->field;
    }

    public static function build(string $statement, int|null & $offset, string $operator): self|null {
        $instance = new self($statement, $offset);
        if (in_array($operator, self::$columnWildcards)) {
            $instance->field = $operator;
            return $instance;
        } else if ($instance->detect($operator)) {
            $field = $instance->parseString($operator);
            $instance->parse($field);
            return $instance;
        }
        return null;
    }

    public static function buildByWord(string $statement, int|null & $offset, string $field): self|null {
        $instance = new self($statement, $offset);
        $instance->parse($field);
        return $instance;
    }
}
