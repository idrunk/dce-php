<?php
/**
 * Author: Drunk
 * Date: 2019-12-20 15:15
 */

namespace dce\sharding\parser\mysql;

use dce\base\ParserTraverResult;
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

        if ($modifier = $this->preParseModifier())
            $this->modifiers[] = $modifier;

        $this->traverse(function ($operator) {
            $this->arguments[] = $this->parseByOperator($operator);
        }, function ($word) {
            $this->arguments[] = $this->parseByWord($word);
        }, function () {
            $paramSeparator = $this->parseCompareExpression($this->preParseOperator());
            $isParamSeparator = in_array($paramSeparator, self::$paramSeparators);
            if (! $isParamSeparator && ! in_array($paramSeparator, self::$closeBrackets)) {
                // 如果后续为参数分隔符, 则继续解析下一个参数, 若为收括号, 则结束当前函数解析, 但若为其他符号或非符号, 则表示当前语句不合法
                throw (new StatementParserException(StatementParserException::FUNCTION_UNCLOSE))->format($this->name);
            }
            return $isParamSeparator ? ParserTraverResult::Step : ParserTraverResult::Break;
        });
    }

    /** @note 本方法为临时方法，由于聚合函数中常常需要用到比较表达式，为了支持简单的表达式而写了这个方法，后续实现比较表达式解析后需替换掉此方法 */
    private function parseCompareExpression(string $paramSeparator): string {
        if (in_array($paramSeparator, MysqlCompareExpressionParser::Operators) && false !== $rightFirst = $this->preParseWord()) {
            $right = $this->parseByWord($rightFirst);
            $left = array_pop($this->arguments);
            $this->arguments[] = MysqlCompareExpressionParser::build($left, $paramSeparator, $right);
            $paramSeparator = $this->preParseOperator();
        }
        return $paramSeparator;
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

    public static function from(string $statement): static|null {
        return preg_match('/^\s*(\w+)(\b)/', $statement, $matches, PREG_OFFSET_CAPTURE)
            ? self::build($statement, $matches[2][1], $matches[1][0]) : null;
    }
}
