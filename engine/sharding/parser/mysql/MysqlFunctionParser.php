<?php
/**
 * Author: Drunk
 * Date: 2019-12-20 15:15
 */

namespace dce\sharding\parser\mysql;

use dce\sharding\parser\MysqlParser;
use dce\sharding\parser\StatementParserException;

class MysqlFunctionParser extends MysqlParser {
    public string $name;

    /** @var string[]|null */
    public array|null $modifiers = null;

    /** @var MysqlParser[] */
    public array $arguments = [];

    public bool $isAggregate = false;

    private static array $aggregateFunctions = ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX'];

    public function detect(): bool {
        $offsetBak = $this->offset;
        $operator = $this->preParseOperator();
        if (! in_array($operator, self::$openBrackets)) {
            $this->offset = $offsetBak;
            return false;
        }
        return true;
    }

    public function parse(string $name): void {
        $this->name = strtoupper($name);
        $this->isAggregate = in_array($this->name, self::$aggregateFunctions);

        if ($modifier = $this->preParseModifier()) {
            $this->modifiers[] = $modifier;
        }

        $this->traverse(function ($operator) {
            $this->arguments[] = $this->parseByOperator($operator);
        }, function ($word) {
            $this->arguments[] = $this->parseByWord($word);
        }, function () {
            $paramSeparator = $this->preParseOperator();
            $isParamSeparator = in_array($paramSeparator, self::$paramSeparators);
            if (! $isParamSeparator && ! in_array($paramSeparator, self::$closeBrackets)) {
                testDump($paramSeparator, $this->offset, "方法'{$this->name}'调用未正常闭合");
                // 如果后续为参数分隔符, 则继续解析下一个参数, 若为收括号, 则结束当前函数解析, 但若为其他符号或非符号, 则表示当前语句不合法
                throw new StatementParserException("方法'{$this->name}'调用未正常闭合");
            }
            return $isParamSeparator ? self::TRAVERSE_CALLBACK_STEP : self::TRAVERSE_CALLBACK_BREAK;
        });
    }

    /**
     * 提取聚合函数
     * @return MysqlFunctionParser[]
     */
    public function extractAggregates(): array {
        $aggregates = [];
        if ($this->isAggregate) {
            // 聚合函数是不能嵌套滴, 所以如果本身就是聚合函数, 就不继续往下找了
            $aggregates[] = $this;
        } else {
            foreach ($this->arguments as $argument) {
                if ($argument instanceof MysqlFunctionParser) {
                    if ($argument->isAggregate) {
                        $aggregates[] = $argument;
                    } else {
                        $aggregates = array_merge($aggregates, $argument->extractAggregates());
                    }
                } else if ($argument instanceof MysqlStatementParser) {
                    $aggregates = array_merge($aggregates, $argument->extractAggregates());
                }
            }
        }
        return $aggregates;
    }

    public function toArray(): array {
        $argumentsToArray = [];
        foreach ($this->arguments as $argument) {
            $argumentsToArray[] = $argument->toArray();
        }
        return [
            'type' => 'function',
            'name' => $this->name,
            'modifiers' => $this->modifiers,
            'arguments' => $argumentsToArray,
        ];
    }

    public function __toString(): string {
        $function = $this->name . '(';
        if ($this->modifiers) {
            $function .= implode(' ', $this->modifiers) . ' ';
        }
        $function .= implode(',', $this->arguments) . ')';
        return $function;
    }

    public static function build(string $statement, int|null & $offset, string $name): static|null {
        $instance = new static($statement, $offset);
        if ($instance->detect()) {
            $instance->parse($name);
            return $instance;
        }
        return null;
    }
}
