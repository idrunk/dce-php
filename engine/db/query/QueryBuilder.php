<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/7/11 11:33
 */

namespace dce\db\query;

use dce\db\query\builder\RawBuilder;
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
use dce\db\query\builder\statement\DeleteStatement;
use dce\db\query\builder\statement\InsertSelectStatement;
use dce\db\query\builder\statement\InsertStatement;
use dce\db\query\builder\statement\SelectStatement;
use dce\db\query\builder\statement\UpdateStatement;

class QueryBuilder {
    private TableSchema $tableSchema;

    private JoinSchema $joinSchema;

    private WhereSchema $whereSchema;

    private GroupSchema $groupSchema;

    private HavingSchema $havingSchema;

    private OrderSchema $orderSchema;

    private LimitSchema $limitSchema;

    private UnionSchema $unionSchema;

    public function getTableSchema(): TableSchema {
        return $this->tableSchema ??= new TableSchema();
    }

    public function getJoinSchema(): JoinSchema {
        return $this->joinSchema ??= new JoinSchema();
    }

    public function getWhereSchema(): WhereSchema {
        return $this->whereSchema ??= new WhereSchema();
    }

    public function getGroupSchema(): GroupSchema {
        return $this->groupSchema ??= new GroupSchema();
    }

    public function getHavingSchema(): HavingSchema {
        return $this->havingSchema ??= new HavingSchema();
    }

    public function getOrderSchema(): OrderSchema {
        return $this->orderSchema ??= new OrderSchema();
    }

    public function getLimitSchema(): LimitSchema {
        return $this->limitSchema ??= new LimitSchema();
    }

    public function getUnionSchema(): UnionSchema {
        return $this->unionSchema ??= new UnionSchema();
    }

    public function addTable(string|RawBuilder|SelectStatement $tableName, string|null $alias): self {
        if ($tableName instanceof TableSchema) {
            $this->tableSchema = $tableName;
        } else {
            $this->getTableSchema()->addTable($tableName, $alias);
        }
        return $this;
    }

    public function addJoin(string|RawBuilder|SelectStatement$tableName, string|null $alias, string|array|RawBuilder|WhereSchema $on, string $type): self {
        $this->getJoinSchema()->addJoin($tableName, $alias, $on, $type);
        return $this;
    }

    public function addWhere(string|array|RawBuilder|WhereSchema $column, string|int|float|false|RawBuilder|SelectStatement $operator, string|int|float|array|false|RawBuilder|SelectStatement $value, string $logic): self {
        $this->getWhereSchema()->addCondition($column, $operator, $value, $logic);
        return $this;
    }

    public function setGroup(string|array|RawBuilder $columns, bool $isAutoRaw): self {
        $this->getGroupSchema()->setGroup($columns, $isAutoRaw);
        return $this;
    }

    public function addHaving(string|array|RawBuilder|WhereSchema $columnName, string|int|float|false|RawBuilder|SelectStatement $operator, string|int|float|array|false|RawBuilder|SelectStatement $value): self {
        $this->getHavingSchema()->addCondition($columnName, $operator, $value);
        return $this;
    }

    public function addOrder(string|array|RawBuilder $column, string|bool|null $order, bool $isAutoRaw): self {
        $this->getOrderSchema()->addOrder($column, $order, $isAutoRaw);
        return $this;
    }

    public function setLimit(int $limit, int $offset): self {
        $this->getLimitSchema()->setLimit($limit, $offset);
        return $this;
    }

    public function addUnion(SelectStatement|RawBuilder $statement, bool $isAll): self {
        $this->getUnionSchema()->addUnion($statement, $isAll);
        return $this;
    }

    public function buildSelect(string|array|RawBuilder|null $columns, bool $isDistinct, bool $isAutoRaw): SelectStatement {
        $selectSchema = new SelectSchema($columns, $isAutoRaw);
        $selectModifierSchema = new SelectModifierSchema($isDistinct ? 'DISTINCT' : '');
        $selectStatement = new SelectStatement(
            $selectSchema,
            $selectModifierSchema,
            $this->getTableSchema(),
            $this->getJoinSchema(),
            $this->getWhereSchema(),
            $this->getGroupSchema(),
            $this->getHavingSchema(),
            $this->getOrderSchema(),
            $this->getLimitSchema(),
            $this->getUnionSchema()
        );
        return $selectStatement;
    }

    public function buildInsert(array $data, bool|null $ignoreOrReplace): InsertStatement {
        $insertSchema = new InsertSchema($data);
        $insertStatement = new InsertStatement($this->getTableSchema(), $insertSchema, $ignoreOrReplace);
        return $insertStatement;
    }

    public function buildInsertSelect(SelectStatement|RawBuilder $selectStatement, string|array $columns, bool|null $ignoreOrReplace): InsertSelectStatement {
        $insertSelectSchema = new InsertSelectSchema($selectStatement, $columns);
        $insertStatement = new InsertSelectStatement($this->getTableSchema(), $insertSelectSchema, $ignoreOrReplace);
        return $insertStatement;
    }

    public function buildUpdate(array $data, bool|null $allowEmptyConditionOrMustEqual): UpdateStatement {
        $updateSchema = new UpdateSchema($data);
        $updateStatement = new UpdateStatement(
            $this->getTableSchema(),
            $this->getJoinSchema(),
            $updateSchema,
            $this->getWhereSchema(),
            $this->getOrderSchema(),
            $this->getLimitSchema(),
            $allowEmptyConditionOrMustEqual
        );
        return $updateStatement;
    }

    public function buildDelete(string|array|null $tableNames, bool|null $allowEmptyConditionOrMustEqual): DeleteStatement {
        $deleteSchema = new DeleteSchema($tableNames);
        $deleteStatement = new DeleteStatement(
            $deleteSchema,
            $this->getTableSchema(),
            $this->getJoinSchema(),
            $this->getWhereSchema(),
            $this->getOrderSchema(),
            $this->getLimitSchema(),
            $allowEmptyConditionOrMustEqual
        );
        return $deleteStatement;
    }
}
