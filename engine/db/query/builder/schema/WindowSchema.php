<?php
namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;

class WindowSchema extends SchemaAbstract {
    /**
     * @param string $name
     * @param string|array|RawBuilder|null $partition
     * @param string|array|RawBuilder|null $order
     * @param string|RawBuilder|null $frame
     * @param string|null $reference
     * @throws QueryException
     */
    public function addWindow(string $name, string|array|RawBuilder|null $partition = null, string|array|RawBuilder|null $order = null, string|RawBuilder|null $frame = null, string $reference = null): void {
        $parts = [false, false, false, false];
        $reference && $parts[0] = $reference;
        if ($partition) {
            $parts[1] = new PartitionSchema();
            $parts[1]->setPartition($partition);
            $this->mergeParams($parts[1]->getParams());
        }
        if ($order) {
            $parts[2] = new OrderSchema();
            $parts[2]->addOrder($order, null, false);
            $this->mergeParams($parts[2]->getParams());
        }
        if ($frame) {
            $parts[3] = new FrameSchema();
            $parts[3]->setFrame($frame);
            $this->mergeParams($parts[3]->getParams());
        }
        $this->pushCondition([$name, $parts]);
    }

    public function __toString(): string {
        return implode(', ', array_map(fn($c) => "$c[0] AS (" .
            implode(' ', array_filter([$c[1][0], $c[1][1] ? "PARTITION BY {$c[1][1]}" : false, $c[1][2] ? "ORDER BY {$c[1][2]}" : false, $c[1][3]]))
            . ')', $this->getConditions()));
    }
}
