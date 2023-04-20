<?php
namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;

class PartitionSchema extends GroupSchema {
    /**
     * @param string|array|RawBuilder $columns
     * @throws QueryException
     */
    public function setPartition(string|array|RawBuilder $columns): void {
        parent::setGroup($columns, false);
    }
}
