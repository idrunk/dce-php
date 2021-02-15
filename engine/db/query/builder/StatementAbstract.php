<?php
/**
 * Author: Drunk
 * Date: 2019/8/20 18:54
 */

namespace dce\db\query\builder;

use dce\db\query\builder\schema\DeleteSchema;
use dce\db\query\builder\schema\GroupSchema;
use dce\db\query\builder\schema\HavingSchema;
use dce\db\query\builder\schema\InsertSchema;
use dce\db\query\builder\schema\InsertSelectSchema;
use dce\db\query\builder\schema\JoinSchema;
use dce\db\query\builder\schema\LimitSchema;
use dce\db\query\builder\schema\OrderSchema;
use dce\db\query\builder\schema\SelectModifierSchema;
use dce\db\query\builder\schema\SelectSchema;
use dce\db\query\builder\schema\TableSchema;
use dce\db\query\builder\schema\UnionSchema;
use dce\db\query\builder\schema\UpdateSchema;
use dce\db\query\builder\schema\WhereSchema;

abstract class StatementAbstract implements StatementInterface {
    private array $params = [];

    private bool $subQueryBool = false;

    protected InsertSchema|null $insertSchema = null;

    protected InsertSelectSchema|null $insertSelectSchema = null;

    protected UpdateSchema|null $updateSchema = null;

    protected SelectSchema|null $selectSchema = null;

    protected DeleteSchema|null $deleteSchema = null;

    protected SelectModifierSchema|null $selectModifierSchema = null;

    protected TableSchema|null $tableSchema = null;

    protected JoinSchema|null $joinSchema = null;

    protected WhereSchema|null $whereSchema = null;

    protected GroupSchema|null $groupSchema = null;

    protected HavingSchema|null $havingSchema = null;

    protected OrderSchema|null $orderSchema = null;

    protected LimitSchema|null $limitSchema = null;

    protected UnionSchema|null $unionSchema = null;

    final public function getParams(): array {
        return $this->params;
    }

    protected function mergeParams(array $params) {
        $this->params = array_merge($this->params, $params);
    }

    public function hasSubQuery(): bool {
        return $this->subQueryBool;
    }

    protected function logHasSubQuery(bool $isSubQuery): void {
        if (! $this->subQueryBool && $isSubQuery) {
            $this->subQueryBool = true;
        }
    }

    public function isSimpleQuery(): bool {
        return ! $this->hasSubQuery()
            && $this->getTableSchema() && 1 === count($this->getTableSchema()->getConditions())
            && (! $this->getJoinSchema() || $this->getJoinSchema()->isEmpty())
            && (! $this->getHavingSchema() || $this->getHavingSchema()->isEmpty());
    }

    public function getInsertSchema(): InsertSchema|null {
        return $this->insertSchema;
    }

    public function getInsertSelectSchema(): InsertSelectSchema|null {
        return $this->insertSelectSchema;
    }

    public function getUpdateSchema(): UpdateSchema|null {
        return $this->updateSchema;
    }

    public function getSelectSchema(): SelectSchema|null {
        return $this->selectSchema;
    }

    public function getDeleteSchema(): DeleteSchema|null {
        return $this->deleteSchema;
    }

    public function getSelectModifierSchema(): SelectModifierSchema|null {
        return $this->selectModifierSchema;
    }

    public function getTableSchema(): TableSchema|null {
        return $this->tableSchema;
    }

    public function getJoinSchema(): JoinSchema|null {
        return $this->joinSchema;
    }

    public function getWhereSchema(): WhereSchema|null {
        return $this->whereSchema;
    }

    public function getGroupSchema(): GroupSchema|null {
        return $this->groupSchema;
    }

    public function getHavingSchema(): HavingSchema|null {
        return $this->havingSchema;
    }

    public function getOrderSchema(): OrderSchema|null {
        return $this->orderSchema;
    }

    public function getLimitSchema(): LimitSchema|null {
        return $this->limitSchema;
    }

    public function getUnionSchema(): UnionSchema|null {
        return $this->unionSchema;
    }

    final public function buildRaw(bool $iKnowThisJustForReference = false): string {
        $params = $this->getParams();
        $sql = self::fill($this, $params);
        if (! $iKnowThisJustForReference) {
            throw new \Exception("\n当前语句未做转义, 不安全, 请勿用于真实查询中, 仅作调试参考 (若不想抛出此异常, 请传入参数true)\n$sql\n");
        }
        return $sql;
    }

    public static function fill($statement, array|null $params): string {
        if ($params) {
            $statement = str_replace('?', '%s', $statement);
            array_walk($params, function (&$param) {
                if (is_string($param)) {
                    $param = "'" . self::quote($param) . "'";
                }
            });
        } else {
            return $statement;
        }
        return sprintf($statement, ... $params);
    }

    public static function quote($value): string {
        // mark need to fill function
        return $value;
    }

    abstract protected function valid(): void;
}
