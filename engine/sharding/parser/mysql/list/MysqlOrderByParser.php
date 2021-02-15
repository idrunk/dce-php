<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/12/22 5:02
 */

namespace dce\sharding\parser\mysql\list;

use dce\sharding\parser\mysql\MysqlListParser;
use dce\sharding\parser\mysql\statement\MysqlOrderByConditionParser;
use dce\sharding\parser\MysqlParser;

class MysqlOrderByParser extends MysqlListParser {
    /** @var MysqlOrderByConditionParser[] */
    public array $conditions = [];

    protected function parse(): void {
        while ($this->offset < $this->statementLength) {
            $this->conditions[] = MysqlOrderByConditionParser::build($this->statement, $this->offset);
            $nextSeparator = $this->preParseOperator();
            if (! in_array($nextSeparator, self::$paramSeparators)) {
                break;
            }
        }
    }

    public function addItem(MysqlParser $item): void {
        if ($item instanceof MysqlOrderByConditionParser) {
            $this->conditions[] = $item;
        }
    }

    public function toArray(): array {
        $conditionsToArray = [];
        foreach ($this->conditions as $condition) {
            $conditionsToArray[] = $condition->toArray();
        }
        return [
            'type' => 'list',
            'class' => 'order',
            'conditions' => $conditionsToArray,
        ];
    }

    public function __toString(): string {
        $columns = implode(',', $this->conditions);
        return $columns;
    }

    protected static function queueProperty(): string {
        return 'conditions';
    }
}
