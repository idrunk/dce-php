<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/12/22 4:13
 */

namespace dce\sharding\parser\mysql\statement;

use dce\sharding\parser\mysql\MysqlFunctionParser;
use dce\sharding\parser\mysql\MysqlStatementParser;
use dce\sharding\parser\MysqlParser;
use dce\sharding\parser\StatementParserException;

class MysqlCaseParser extends MysqlStatementParser {
    public MysqlParser $case;

    /** @var MysqlParser[] */
    public array $when = [];

    public MysqlParser|null $else = null;

    protected function parse(): void {
        $this->case = $this->parseWithOffset(null);

        $this->traverse(function ($operator) {
            if (! in_array($operator, self::$partSeparators)) {
                throw (new StatementParserException(StatementParserException::INVALID_OPERATOR_CASE_UNCLOSE))->format($operator);
            }
        }, function ($word) {
            $wordUpper = strtoupper($word);
            if (self::$whenStmt === $wordUpper) {
                $this->when[] = $this->parseByWord($word, [MysqlWhenParser::class]);
            } else if ('ELSE' === $wordUpper) {
                $this->else = $this->parseWithOffset(null);
            } else if ('END' === $wordUpper) {
                return self::TRAVERSE_CALLBACK_BREAK;
            } else {
                throw (new StatementParserException(StatementParserException::INVALID_STATEMENT_CASE_UNCLOSE))->format($word);
            }
        });
    }

    /**
     * 提取聚合函数
     * @return MysqlFunctionParser[]
     */
    public function extractAggregates(): array {
        $aggregates = [];
        $aggregates = array_merge($aggregates, $this->case->extractAggregates());
        foreach ($this->when as $when) {
            $aggregates = array_merge($aggregates, $when->extractAggregates());
        }
        if ($this->else instanceof MysqlFunctionParser) {
            $aggregates = array_merge($aggregates, $this->else->extractAggregates());
        }
        return $aggregates;
    }

    public function toArray(): array {
        $whenToArray = [];
        foreach ($this->when as $when) {
            $whenToArray[] = $when->toArray();
        }
        return [
            'type' => 'statement',
            'statement' => 'case',
            'case' => $this->case->toArray(),
            'when' => $whenToArray,
            'else' => $this->else ? $this->else->toArray(): null,
        ];
    }

    public function __toString(): string {
        $case = "CASE {$this->case} " . implode(' ', $this->when);
        if ($this->else) {
            $case .= " ELSE {$this->else}";
        }
        $case .= ' END';
        return $case;
    }
}
