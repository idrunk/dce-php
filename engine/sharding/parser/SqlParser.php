<?php
/**
 * Author: Drunk
 * Date: 2019/10/25 14:18
 */

namespace dce\sharding\parser;

abstract class SqlParser extends StatementParser {
    protected const TRAVERSE_CALLBACK_EXCEPTION = 0;

    protected const TRAVERSE_CALLBACK_RETURN = 1;

    protected const TRAVERSE_CALLBACK_BREAK = 2;

    protected const TRAVERSE_CALLBACK_CONTINUE = 3;

    protected const TRAVERSE_CALLBACK_STEP = 4;

    protected const BACKSLASH = '\\'; // 反斜杠

    protected static array $openBrackets = ['(',];

    protected static array $closeBrackets = [')'];

    /** @var string[] 成份分隔符 */
    protected static array $partSeparators = ['', ' ', "\t", "\r", "\n"];

    /** @var string[] 参数分隔符 */
    protected static array $paramSeparators = [','];

    protected function parseString(string $quoteOpener): string {
        $string = '';
        $isClosed = false;
        $lastBackslashCounter = 0;
        for (; $this->offset < $this->statementLength; $this->offset ++) {
            $char = mb_substr($this->statement, $this->offset, 1);
            if ($char === $quoteOpener && ! $lastBackslashCounter % 2) {
                $isClosed = true;
                break;
            } else {
                if (self::BACKSLASH === $char) {
                    $lastBackslashCounter ++;
                } else {
                    $lastBackslashCounter = 0;
                }
                $string .= $char;
            }
        }
        if (! $isClosed) {
            $string = mb_substr($string, 0, 10);
            throw new StatementParserException("字符串'{$string}'未闭合");
        }
        $this->offset ++;
        return $string;
    }

    protected function parseWord(string $char, bool $needParseDecimal = true): string {
        $offset = $this->offset + 1;
        $word = $char;
        for (; $offset < $this->statementLength; $offset ++) {
            $char = mb_substr($this->statement, $offset, 1);
            if ($this->isBoundary($char)) {
                break;
            }
            $word .= $char;
        }
        if ($needParseDecimal && ctype_digit($word)) {
            // 尝试处理小数
            // 碰到非word才break的, 所以下个char就在当前偏移处
            $nextChar = mb_substr($this->statement, $offset, 1);
            if ('.' === $nextChar) {
                $this->offset = $offset + 1;
                $nextNextChar = mb_substr($this->statement, $this->offset, 1);
                if (! $this->isBoundary($nextNextChar)) {
                    $nextWord = $this->parseWord($nextNextChar, false);
                    if (ctype_digit($nextWord)) {
                        $word .= ".{$nextWord}";
                        $offset = $this->offset;
                    }
                }
            }
        }
        $this->offset = $offset;
        return $word;
    }

    protected function parseOperator(string $operator, array $allowedCompoundOperators = []): string {
        $hasCompoundOperator = !! $allowedCompoundOperators;
        $compoundOperator = $compoundOperatorTemp = in_array($operator, self::$partSeparators) ? '' : $operator;
        if ($compoundOperator && ! $hasCompoundOperator) {
            // 如果无复合符号, 且当前为非空格的符号, 则表示当前即有效符号, 且需将游标前移
            $this->offset ++;
            return $compoundOperator;
        }

        $hitOffset = $offset = $this->offset;
        $operatorCounter = strlen($compoundOperator);
        for (; $offset < $this->statementLength; $offset ++) {
            $char = mb_substr($this->statement, $offset, 1);
            if (! $this->isBoundary($char)) {
                break;
            }
            if (in_array($char, self::$partSeparators)) {
                if (1 === $operatorCounter || $compoundOperatorTemp === $compoundOperator) {
                    $operator = $char;
                    $hitOffset = $offset; // 如果是当前的空格在第一个符号或者组合符号后面, 则更新游标位置到当前空格
                }
                continue;
            }

            $operatorCounter ++;
            $compoundOperatorTemp .= $char;

            if ($hasCompoundOperator) {
                if (in_array($compoundOperatorTemp, $allowedCompoundOperators)) {
                    $hitOffset = $offset; // 如果当前为复合符号, 则更新游标位置并记录之
                    $compoundOperator = $compoundOperatorTemp;
                } else if (1 === $operatorCounter) {
                    $operator = $char;
                    $hitOffset = $offset; // 如果是第一个符号, 则更新游标位置
                }
            } else {
                $operator = $char;
                $hitOffset = $offset; // 如果不是复合符号, 则更新游标位置并直接返回符号
                break;
            }
        }
        // 将游标前移
        $this->offset = $hitOffset + 1;
        // 有复合符号则返回复合符号, 没有则返回第一个符号
        return $compoundOperator ?: $operator;
    }

    protected function isBoundary(string $char): bool {
        $ord = ord($char);
        return $char === ''
            || ($ord <= 47)
            || ($ord <= 64 && $ord >= 58)
            || ($ord === 96 || $ord <= 94 && $ord >= 91)
            || ($ord <= 126 && $ord >= 123);
    }
}
