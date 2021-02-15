<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/12/22 4:56
 */

namespace dce\sharding\parser\mysql\list;

use dce\sharding\parser\mysql\MysqlListParser;
use dce\sharding\parser\MysqlParser;

class MysqlGroupByParser extends MysqlListParser {
    /** @var MysqlParser[] */
    public array $conditions = [];

    protected function parse(): void {
        while ($this->offset < $this->statementLength) {
            $this->conditions[] = $this->parseWithOffset();
            $nextSeparator = $this->preParseOperator();
            if (! in_array($nextSeparator, self::$paramSeparators)) {
                break;
            }
        }
    }

    public function addItem(MysqlParser $item): void {
        $this->conditions[] = $item;
    }

    public function toArray(): array {
        $conditionsToArray = [];
        foreach ($this->conditions as $condition) {
            $conditionsToArray[] = $condition->toArray();
        }
        return [
            'type' => 'list',
            'class' => 'group',
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
