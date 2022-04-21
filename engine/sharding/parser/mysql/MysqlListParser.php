<?php
/**
 * Author: Drunk
 * Date: 2019-12-20 15:18
 */

namespace dce\sharding\parser\mysql;

use dce\sharding\parser\MysqlParser;
use Iterator;

abstract class MysqlListParser extends MysqlParser implements Iterator {
    private int $queueOffset = 0;

    /**
     * 提取聚合函数
     * @return MysqlFunctionParser[]
     */
    public function extractAggregates(): array {
        $aggregates = [];
        foreach ($this as $mysqlParser)
            $aggregates = array_merge($aggregates, $mysqlParser->extractAggregates());
        return $aggregates;
    }

    public function current(): MysqlParser {
        return $this->getQueue()[$this->queueOffset];
    }

    public function next(): void {
        $this->queueOffset ++;
    }

    public function key(): int {
        return $this->queueOffset;
    }

    public function valid(): bool {
        return $this->queueOffset < count($this->getQueue());
    }

    public function rewind(): void {
        $this->queueOffset = 0;
    }

    public function delItem(int $offset, int $length = 1): void {
        array_splice($this->getQueue(), $offset, $length);
    }

    /**
     * @return MysqlParser[]
     */
    private function & getQueue(): array {
        $property = static::queueProperty();
        return $this->$property;
    }

    public static function build(string $statement, int & $offset = 0): static {
        $instance = new static($statement, $offset);
        $instance->parse();
        return $instance;
    }

    abstract protected function parse(): void;

    abstract public function addItem(MysqlParser $item): void;

    abstract protected static function queueProperty(): string;
}
