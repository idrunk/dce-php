<?php
/**
 * Author: Drunk
 * Date: 2019-12-20 15:24
 */

namespace dce\sharding\parser\mysql\statement;

use dce\sharding\parser\mysql\MysqlFieldParser;
use dce\sharding\parser\mysql\MysqlFunctionParser;
use dce\sharding\parser\mysql\MysqlStatementParser;
use dce\sharding\parser\MysqlParser;

class MysqlColumnItemParser extends MysqlStatementParser {
    public MysqlParser $field;

    public string|null $alias = null;

    protected function parse(): void {
        $this->field = $this->parseWithOffset();
        if (! $this->field instanceof MysqlFieldParser || ! in_array($this->field->field, self::$columnWildcards)) {
            // 非通配符才可能有别名
            $this->alias = $this->preParseAlias();
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
            'statement' => 'column',
            'field' => $this->field->toArray(),
            'alias' => $this->alias,
        ];
    }

    public function __toString(): string {
        $field = $this->field;
        if ($this->alias) {
            $field .= " AS {$this->alias}";
        }
        return $field;
    }
}
