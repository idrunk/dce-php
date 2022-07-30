<?php
/**
 * Author: Drunk
 * Date: 2019/7/30 11:16
 */

namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;

class InsertUpdateSchema extends InsertSchema {
    private UpdateSchema $updateSchema;

    public function __construct(array $data) {
        parent::__construct($data);
        $this->isBatchInsert() && throw new QueryException(QueryException::NOT_ALLOW_BATCH_INSERT_UPDATE);
        $this->updateSchema = new UpdateSchema($data);
        $this->mergeParams($this->updateSchema->getParams());
    }

    public function __toString(): string {
        $insertSql = parent::__toString();
        return "$insertSql ON DUPLICATE KEY UPDATE $this->updateSchema";
    }
}
