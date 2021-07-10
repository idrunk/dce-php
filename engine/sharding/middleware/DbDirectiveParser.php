<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/10/7 12:01
 */

namespace dce\sharding\middleware;

use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\schema\DeleteSchema;
use dce\db\query\builder\schema\GroupSchema;
use dce\db\query\builder\schema\InsertSchema;
use dce\db\query\builder\schema\LimitSchema;
use dce\db\query\builder\schema\OrderSchema;
use dce\db\query\builder\schema\SelectModifierSchema;
use dce\db\query\builder\schema\SelectSchema;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\Statement\DeleteStatement;
use dce\db\query\builder\Statement\InsertSelectStatement;
use dce\db\query\builder\Statement\InsertStatement;
use dce\db\query\builder\Statement\SelectStatement;
use dce\db\query\builder\Statement\UpdateStatement;
use dce\db\query\builder\StatementAbstract;
use dce\db\query\builder\StatementInterface;
use dce\Dce;
use dce\sharding\parser\mysql\list\MysqlColumnParser;
use dce\sharding\parser\mysql\list\MysqlGroupByParser;
use dce\sharding\parser\mysql\list\MysqlOrderByParser;
use dce\sharding\parser\mysql\MysqlFunctionParser;
use dce\sharding\parser\MysqlParser;

class DbDirectiveParser extends DirectiveParser {
    private string|null $tableName;

    private bool $shardingBool = false;

    private bool $insertBool = false;

    private bool $updateBool = false;

    private bool $selectBool = false;

    private bool $writeBool = false;

    private array $dataToSave;

    private array $whereConditions;

    public MysqlColumnParser|null $selectColumn = null;

    public MysqlGroupByParser|null $groupBy = null;

    public MysqlOrderByParser|null $orderBy = null;

    /** @var MysqlParser[]|null */
    public array|null $shardingSelectColumns = null;

    public array|null $limitConditions = null;

    public array|null $selectModifiers = null;

    public function __construct(
        private StatementInterface|string $statement,
        private array|null $params,
    ) {
        if ($statement instanceof StatementAbstract) {
            if ($statement->getTableSchema()) {
                $this->tableName = $statement->getTableSchema()->getName();
                $shardingConfig = $this->getSharding();
                // 能根据表名渠道分库配置, 则表示为分库表, 需分库查询
                if ($shardingConfig) {
                    // 若为允许连表的按模分库查询, 或者非查询式插入的简单查询, 则为有效分库查询
                    if (
                        $shardingConfig->allowJoint && $shardingConfig->isModulo()
                        || ! $statement instanceof InsertSelectStatement && $statement->isSimpleQuery()
                    ) {
                        $this->shardingBool = true;
                        $this->insertBool = $statement instanceof InsertStatement;
                        $this->updateBool = $statement instanceof UpdateStatement;
                        $this->selectBool = $statement instanceof SelectStatement;
                        if ($this->insertBool) {
                            $this->dataToSave = $statement->getInsertSchema()->getConditions();
                        } else {
                            if ($this->updateBool) {
                                $this->dataToSave = $statement->getUpdateSchema()->getConditions()[0];
                            }
                            $this->whereConditions = $statement->getWhereSchema()->getConditions();
                        }
                    } else if ($shardingConfig->allowJoint) {
                        throw new MiddlewareException(MiddlewareException::NO_MOD_SHARDING_NO_JOINT);
                    } else {
                        throw new MiddlewareException(MiddlewareException::ALLOW_JOINT_NOT_OPEN);
                    }
                }
                if (! $statement instanceof SelectStatement) {
                    $this->writeBool = true;
                }
            }
        } else {
            $pattern = '/^\s*(?:SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD|COPY|ALTER|RENAME|GRANT|REVOKE|LOCK|UNLOCK|REINDEX)\b/i';
            $this->writeBool = preg_match($pattern, $statement);
        }
    }

    public function buildInsert(array $data): InsertStatement {
        $statement = $this->getStatement();
        $insertSchema = new InsertSchema($data);
        $tableSchema = $statement->getTableSchema();
        $ignoreOrReplace = $this->insertBool ? $statement->getIgnoreOrReplace(): null;
        return new InsertStatement($tableSchema, $insertSchema, $ignoreOrReplace);
    }

    public function buildDelete(array $where): DeleteStatement {
        $statement = $this->getStatement();
        $deleteSchema = new DeleteSchema(null);
        $tableSchema = $statement->getTableSchema();
        $joinSchema = $statement->getJoinSchema();
        $whereSchema = new WhereSchema();
        $whereSchema->addCondition($this->whereConditions, false, false, 'AND');
        $whereSchema->addCondition($where, false, false, 'AND');
        $orderSchema = $statement->getOrderSchema();
        $limitSchema = $statement->getLimitSchema();
        return new DeleteStatement($deleteSchema, $tableSchema, $joinSchema, $whereSchema, $orderSchema, $limitSchema, false);
    }

    public function buildShardingSelect(): SelectStatement|null {
        if ($this->selectBool) {
            $statement = $this->getStatement();
            $selectSchema = $statement->getSelectSchema();
            $selectModifierSchema = $statement->getSelectModifierSchema();
            $tableSchema = $statement->getTableSchema();
            $joinSchema = $statement->getJoinSchema();
            $whereSchema = $statement->getWhereSchema();
            $groupSchema = $statement->getGroupSchema();
            $havingSchema = $statement->getHavingSchema();
            $orderSchema = $statement->getOrderSchema();
            $limitSchema = $statement->getLimitSchema();
            $limitOffset = $limitSchema->getConditions();
            $limitSchemaSharding = new LimitSchema();
            $limitSchemaSharding->setLimit(($limitOffset[0] ?? 0) + ($limitOffset[1] ?? 0), 0);
            $unionSchema = $statement->getUnionSchema();
            $selectSchemaSharding = $this->parseMergeConditions($selectSchema, $groupSchema, $orderSchema, $limitSchema, $selectModifierSchema);
            return new SelectStatement($selectSchemaSharding, $selectModifierSchema, $tableSchema, $joinSchema, $whereSchema, $groupSchema, $havingSchema, $orderSchema, $limitSchemaSharding, $unionSchema);
        }
        return null;
    }

    private function parseMergeConditions(SelectSchema $selectSchema, GroupSchema $groupSchema, OrderSchema $orderSchema, LimitSchema $limitSchema, SelectModifierSchema|null $selectModifierSchema = null): SelectSchema {
        $selectColumnItems = []; // 真实落库的查询列解析对象集 (供后续做合并计算时做依赖)
        $selectFieldsString = []; // 原始查询列的字符串集 (供后续解析分组排序时比对是否需将分组排序条件列加入分库查询中)
        $selectAggregatesString = []; // 聚合函数列字符串集 (与下述avg集联合使用)
        $avgAggregates = []; // avg聚合函数字符串集 (与上述聚合函数集搭配使用, 判断是否有计算avg函数值必备的却又缺失的条件聚合函数, 并组装该必备函数加入落库查询列集)

        // 处理查询列, 解析并队列缓存, 并队列记录聚合函数, 供后续的依赖计算
        $this->selectColumn = MysqlColumnParser::build($selectSchema);
        foreach ($this->selectColumn as $column) {
            $aggregates = $column->extractAggregates();
            if ($aggregates) {
                // 如果有聚合函数, 则提取出来, 供后续做源数据合并时使用 (聚合函数内如果有其他函数或计算, 我们无需处理, 因为我们只合并结果集, 而不做数据库层的数据处理)
                foreach ($aggregates as $aggregate) {
                    if ('AVG' === $aggregate->name) {
                        $avgAggregates[] = $aggregate;
                    }
                    $selectColumnItems[] = $aggregate;
                    $selectFieldsString[] = $selectAggregatesString[] = (string) $aggregate;
                }
            } else {
                $selectColumnItems[] = $column->field;
                $selectFieldsString[] = (string) $column->field;
            }
        }

        // 处理分组列, 扩充查询列
        if (! $groupSchema->isEmpty()) {
            $this->groupBy = MysqlGroupByParser::build($groupSchema);
            // group by中不会有聚合函数
            foreach ($this->groupBy->conditions as $condition) {
                if (! in_array((string) $condition, $selectFieldsString)) {
                    // 将没在查询条件中包含的分组条件加入查询条件, 供后续合并时做分组依据
                    $selectColumnItems[] = $condition;
                }
            }
        }

        // 处理排序列, 扩充查询列
        if (! $orderSchema->isEmpty()) {
            $this->orderBy = MysqlOrderByParser::build($orderSchema);
            foreach ($this->orderBy->conditions as $condition) {
                $aggregates = $condition->extractAggregates();
                $orderFields = [];
                if ($aggregates) {
                    foreach ($aggregates as $aggregate) {
                        if ('AVG' === $aggregate->name) {
                            $avgAggregates[] = $aggregate;
                        }
                        $orderFields[] = $aggregate;
                    }
                } else {
                    $orderFields[] = $condition->field;
                }
                foreach ($orderFields as $field) {
                    if (! in_array((string) $field, $selectFieldsString)) {
                        // 将没在查询条件中包含的排序条件加入查询条件, 供后续合并时排序
                        $selectColumnItems[] = $field;
                    }
                }
            }
        }

        // 处理聚合函数, 若有AVG函数却无必须的SUM与COUNT函数, 则拼入相应函数
        foreach ($avgAggregates as $avgAggregate) {
            $noNameFunction = substr($avgAggregate, 3);
            $sumAggregate = MysqlFunctionParser::build($noNameFunction, $offset, 'SUM');
            $countAggregate = MysqlFunctionParser::build($noNameFunction, $offset2, 'COUNT');
            if (! in_array((string) $sumAggregate, $selectAggregatesString)) {
                $selectColumnItems[] = $sumAggregate;
            }
            if (! in_array((string) $countAggregate, $selectAggregatesString)) {
                $selectColumnItems[] = $countAggregate;
            }
        }

        // 组装真实落库的查询列
        $selectSchemaItems = new RawBuilder(implode(',', $selectColumnItems), false);
        $selectSchemaSharding = new SelectSchema($selectSchemaItems, false);
        $this->shardingSelectColumns = $selectColumnItems;
        $this->limitConditions = $limitSchema->getConditions();
        $this->selectModifiers = $selectModifierSchema->getConditions();

        return $selectSchemaSharding;
    }

    public function isSharding(): bool {
        return $this->shardingBool;
    }

    public function isInsert(): bool  {
        return $this->insertBool;
    }

    public function isUpdate(): bool  {
        return $this->updateBool;
    }

    public function isSelect(): bool  {
        return $this->selectBool;
    }

    public function isWrite(): bool {
        return $this->writeBool;
    }

    public function getConditions(): array|null {
        return $this->whereConditions;
    }

    public function getStoreData(): array|null {
        return $this->dataToSave;
    }

    public function getSharding(string|null $tableName = null): ShardingConfig|null {
        return (Dce::$config->sharding ?? null)?->getConfig($tableName ?: $this->tableName);
    }

    public function getStatement(): StatementAbstract|string {
        return $this->statement;
    }

    public function getParams(): array|null {
        return $this->params;
    }
}
