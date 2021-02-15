<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/12/22 5:09
 */

namespace dce\sharding\parser\mysql\statement;

use dce\sharding\parser\mysql\MysqlFunctionParser;
use dce\sharding\parser\mysql\MysqlStatementParser;
use dce\sharding\parser\MysqlParser;

class MysqlOrderByConditionParser extends MysqlStatementParser {
    public MysqlParser $field;

    public string|null $sort = 'ASC';

    public bool $isAsc = true;

    protected function parse(): void {
        $this->field = $this->parseWithOffset();
        $sort = $this->preParseWord();
        if ($sort) {
            $sortUpper = strtoupper($sort);
            if (in_array($sortUpper, ['DESC', 'ASC'])) {
                $this->sort = $sortUpper;
            } else {
                $this->offset -= mb_strlen($sort);
            }
        }
        if ('ASC' !== $this->sort) {
            $this->isAsc = false;
        }
    }

    /**
     * 提取聚合函数
     * @return MysqlFunctionParser[]
     */
    public function extractAggregates(): array {
        $aggregates = [];
        $aggregates = array_merge($aggregates, $this->field->extractAggregates());
        return $aggregates;
    }

    public function toArray(): array {
        return [
            'type' => 'statement',
            'statement' => 'order',
            'field' => $this->field->toArray(),
            'sort' => $this->sort,
            'is_asc' => $this->isAsc,
        ];
    }

    public function __toString(): string {
        $order = $this->field;
        if (! $this->isAsc) {
            $order .= "{$this->field} DESC";
        }
        return $order;
    }
}
