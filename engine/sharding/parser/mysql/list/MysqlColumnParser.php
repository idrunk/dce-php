<?php
/**
 * Author: Drunk
 * Date: 2019-12-20 15:25
 */

namespace dce\sharding\parser\mysql\list;

use dce\sharding\parser\mysql\MysqlListParser;
use dce\sharding\parser\mysql\statement\MysqlColumnItemParser;
use dce\sharding\parser\MysqlParser;

class MysqlColumnParser extends MysqlListParser {
    /** @var string[] */
    public array $modifiers = [];

    /** @var MysqlColumnItemParser[] */
    public array $columns = [];

    protected function parse(): void {
        ($modifier = $this->preParseModifier()) && $this->modifiers[] = $modifier;

        while ($this->offset < $this->statementLength) {
            $this->columns[] = MysqlColumnItemParser::build($this->statement, $this->offset);
            $nextSeparator = $this->preParseOperator();
            if (! in_array($nextSeparator, self::$paramSeparators)) break;
        }
    }

    /** @return MysqlColumnItemParser */
    public function current(): MysqlColumnItemParser {
        return parent::current();
    }

    public function addItem(MysqlParser $item): void {
        $item instanceof MysqlColumnItemParser && $this->columns[] = $item;
    }

    public function toArray(): array {
        $columnsToArray = [];
        foreach ($this->columns as $column)
            $columnsToArray[] = $column->toArray();
        return [
            'type' => 'list',
            'class' => 'column',
            'modifiers' => $this->modifiers,
            'columns' => $columnsToArray,
        ];
    }

    public function __toString(): string {
        $modifiers = implode(' ', $this->modifiers);
        $columns = implode(',', $this->columns);
        $modifiers && $columns = "{$modifiers} {$columns}";
        return $columns;
    }

    protected static function queueProperty(): string {
        return 'columns';
    }
}
