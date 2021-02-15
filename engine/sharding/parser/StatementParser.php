<?php
/**
 * Author: Drunk
 * Date: 2019/10/25 14:17
 */

namespace dce\sharding\parser;

abstract class StatementParser {
    protected string $statement;

    protected int $statementLength;

    protected int $offset;

    final protected function __construct(string $statement, int|null & $offset = 0) {
        $offset ??= 0;
        $this->statement = trim($statement);
        $this->statementLength = mb_strlen($statement);
        $this->offset = & $offset;
    }

    protected static function isMinus(string $word): bool {
        return '-' === $word;
    }

    abstract public function toArray(): array;

    abstract public function __toString(): string;
}
