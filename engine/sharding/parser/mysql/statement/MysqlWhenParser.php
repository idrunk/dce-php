<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/12/22 4:26
 */

namespace dce\sharding\parser\mysql\statement;

use dce\sharding\parser\mysql\MysqlFunctionParser;
use dce\sharding\parser\mysql\MysqlStatementParser;
use dce\sharding\parser\MysqlParser;
use dce\sharding\parser\StatementParserException;

class MysqlWhenParser extends MysqlStatementParser {
    public MysqlParser $when;

    public MysqlParser $then;

    protected function parse(): void {
        $this->when = $this->parseWithOffset(null);
        $then = $this->preParseWord();
        if (! $then || 'THEN' !== strtoupper($then)) {
            throw new StatementParserException('缺少THEN');
        }
        $this->then = $this->parseWithOffset(null);
    }

    /**
     * 提取聚合函数
     * @return MysqlFunctionParser[]
     */
    public function extractAggregates(): array {
        $aggregates = [];
        $aggregates = array_merge($aggregates, $this->when->extractAggregates());
        $aggregates = array_merge($aggregates, $this->then->extractAggregates());
        return $aggregates;
    }

    public function toArray(): array {
        return [
            'type' => 'statement',
            'statement' => 'when',
            'when' => $this->when->toArray(),
            'then' => $this->then->toArray(),
        ];
    }

    public function __toString(): string {
        $when = "WHEN {$this->when} THEN {$this->then}";
        return $when;
    }
}
