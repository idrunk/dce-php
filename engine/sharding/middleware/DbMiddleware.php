<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/10/7 12:01
 */

namespace dce\sharding\middleware;

use Closure;
use dce\base\SwooleUtility;
use dce\db\connector\DbConnector;
use dce\db\connector\DbPool;
use dce\db\connector\PdoDbConnector;
use dce\db\proxy\TransactionException;
use dce\db\Query;
use dce\db\query\builder\StatementInterface;
use dce\db\query\QueryException;
use dce\db\query\builder\schema\WhereConditionSchema;
use dce\Dce;
use dce\pool\ChannelAbstract;
use dce\sharding\id_generator\IdgException;
use dce\sharding\middleware\data_processor\DbReadProcessor;
use dce\sharding\middleware\data_processor\DbWriteProcessor;
use drunk\Structure;
use Iterator;
use Swoole\Coroutine\Barrier;
use Swoole\Exception;

class DbMiddleware extends Middleware {
    private ShardingConfig $config;

    private int $lastInsertId = 0;

    public function __construct(
        DirectiveParser $directiveParser,
        private ShardingDbProxy $dbProxy,
    ) {
        parent::__construct($directiveParser);
    }

    /**
     * 路由解析, 根据Sql语句判断分库或普通查询, 进行相应后续操作
     * @throws MiddlewareException
     * @throws QueryException
     * @throws IdgException
     * @throws Exception
     */
    protected function shardingQuery(): void {
        $this->thrownChannel = ChannelAbstract::autoNew(64);
        if (! $this->directiveParser->isSharding()) return;

        $isWrite = $this->directiveParser->isWrite();
        $this->config = $this->directiveParser->getSharding();
        $dbMapping = $this->shardingRoute();
        $exceptions = [];
        $barrier = SwooleUtility::inSwoole() ? Barrier::make() : null;
        // Swoole环境则并发查询分库 (不做什么分离设计了, 直接放这直观方便)
        foreach ($dbMapping as $dbAlias => $statementSet) {
            $connectorPool = DbPool::inst($dbAlias, $isWrite)->setConfigs(Dce::$config->mysql->getConfig($dbAlias, $isWrite), false);
            // warn 容器必须放在这层，若放在外层，可能因多次执行导致数据混淆，放在内层则会导致同库并发顺序问题
            // 同库不并发 (除了跨库更新的情况外, 当前查询不会拆为多条, 可以避免可能出现的并发问题)
            // 注册协程自动释放，解决未知原因导致长时间闲置连接导致断开时，PDO::prepare()可能需等待960秒才能释放协程抛出连接已断开的异常的问题
            PdoDbConnector::registerCoroutineAutoReleaseOrHandle($connectorPool->retryableContainer(
                fn() => Structure::forEach($statementSet, fn($statement) => $this->directDistribute($statement, $dbAlias, $connectorPool)), $exceptions, $barrier));
        }
        $barrier && Barrier::wait($barrier);
        $exceptions && throw array_pop($exceptions);
    }

    /**
     * SQL指令分发器
     * @param StatementInterface $statement
     * @param string $dbAlias
     * @param DbPool $connectorPool
     * @throws TransactionException
     */
    private function directDistribute(StatementInterface $statement, string $dbAlias, DbPool $connectorPool): void {
        // 若打开了事务开关, 则尝试开启事务
        $transaction = ShardingTransaction::tryBegin($this->config->alias, $dbAlias, $connectorPool);
        $inTransaction = $transaction instanceof ShardingTransaction;
        $connector = $inTransaction ? $transaction->getConnector() : $transaction;
        if ($this->directiveParser->isSelect()) {
            // 查询语句直接查所有, 后面再合并
            $result = $connector->queryAll($statement);
        } else if ($this->directiveParser->isInsert() && ! $this->directiveParser->getStatement()->getInsertSchema()->isBatchInsert()) {
            // 单条插入返回插入的ID
            $result = $connector->queryGetInsertId($statement) ?: $this->lastInsertId;
        } else {
            // 其他返回影响记录数
            $result = $connector->queryGetAffectedCount($statement);
        }
        if (! $inTransaction) {
            // 非处于事务过程, 则自动将连接放回, 否则连接于事务回滚或提交时才放回
            $connectorPool->put($connector); // 放回连接池
        }
        $this->getProcessor()->merge($result);
    }

    /**
     * 分库路由, 根据待插入数据或者查询条件定位到待操作数据库, 并将库与查询语句关联返回
     * @return array
     * @throws QueryException
     * @throws MiddlewareException|IdgException
     */
    private function shardingRoute(): array {
        $dbMapping = [];
        if ($this->directiveParser->isInsert()) {
            $storeData = $this->directiveParser->getStoreData();
            $dbDataMapping = $this->navigationByStoreData($storeData);
            foreach ($dbDataMapping as $dbAlias => $data) {
                // 插入数据需根据ID路由插入对应数据到对应库, 所以需要重建对应数据的插入语句
                $dbMapping[$dbAlias][] = $this->directiveParser->buildInsert($data);
            }
        } else {
            $statement = $this->directiveParser->getStatement();
            if ($this->directiveParser->isUpdate()) {
                $storeData = $this->directiveParser->getStoreData();
                // 组装跨分库更新迁移sql组件，并将更新迁移的sql组件压入sql语句映射表
                // 凡是需要删除的语句都是先插入了，所以此处不必顾虑删除后异常中断导致数据丢失的问题
                foreach ($this->shardingUpdateTransfer($storeData) as $dbAlias => $statementSet) {
                    foreach ($statementSet as $stmt) {
                        $dbMapping[$dbAlias][] = $stmt;
                    }
                }
            } else if ($this->directiveParser->isSelect()) {
                // 查询语句需要做分库数据合并操作, 所以需要查出所有源数据后做合并处理, 需要重建查询语句
                $statement = $this->directiveParser->buildShardingSelect();
            }
            // 对于更与删, 不作特殊处理, 虽然limit/order等可能影响结果的正确性, 但因为其对于分库来说无意义, 所以不对其进行处理 (后续考虑是否直接禁止limit与order结构)
            $conditions = $this->directiveParser->getConditions();
            $dbSet = $this->navigationByCondition($conditions);
            if (! $dbSet) {
                // 如果无法根据条件定位, 则查全部库
                $dbSet = $this->config->flipMapping;
            }
            foreach ($dbSet as $dbAlias) {
                $dbMapping[$dbAlias][] = $statement;
            }
        }
        return $dbMapping;
    }

    /**
     * 组装跨分库更新迁移sql组件 (分库更新数据时, 可能会更新分库依据字段, 这种字段值改变时可能需要迁库, 此方法即处理这种情况)
     * @param array $upData
     * @return StatementInterface[][]
     * @throws MiddlewareException
     * @throws IdgException
     */
    private function shardingUpdateTransfer(array $upData): array {
        [$upIdValue, $upShardingValue] = [$upData[$this->config->idColumn] ?? null, $upData[$this->config->shardingColumn] ?? null];
        $upValue = $upShardingValue ?? $upIdValue;
        // 如果待储存字段中不包括分库依据字段, 则为普通更新, 不会跨库迁移数据, 无需做特殊处理
        if (null === $upValue) return [];
        ! $this->config->crossUpdate && throw new MiddlewareException(MiddlewareException::OPEN_CROSS_UPDATE_TIP);

        $mapping = $this->config->flipMapping;
        [$upValueColumn, $upValueTag] = null === $upShardingValue ? [$this->config->idColumn, $this->config->idTag] : [$this->config->shardingColumn, $this->config->shardingTag];
        $upDbAlias = $this->config->isModulo() ? $mapping[Dce::$config->idGenerator->mod($this->config->modulus, $upValue, $upValueTag)] : self::rangeMappingEqual($mapping, $upValue)[0];

        $insertDataMapping = $deleteIdsMapping = [];
        $statement = $this->directiveParser->getStatement();
        $oldData = (new Query($this->dbProxy))->table($statement->getTableSchema())->where($statement->getWhereSchema())->select();
        foreach ($oldData as $old) {
            // 如果表中没有有效的分库依据字段值，则无法进行跨库更新
            $oldValue = $old[$upValueColumn] ?? null;
            null === $oldValue && throw (new MiddlewareException(MiddlewareException::SHARDING_VALUE_NOT_SPECIFIED))->format($this->config->tableName, $upValueColumn);
            $oldDbAlias = $this->config->isModulo() ? $mapping[Dce::$config->idGenerator->mod($this->config->modulus, $oldValue, $upValueTag)] : self::rangeMappingEqual($mapping, $oldValue)[0];
            if ($upDbAlias !== $oldDbAlias) {
                $insData = array_merge($old, $upData);

                if ($this->config->shardingColumn && $this->config->idColumn) {
                    if ($upShardingValue && null === $upIdValue) { // 如果传了分库字段值未传ID值
                        if ($this->config->idTag) { // 如果配置了idTag则自动生成新的ID
                            $insData[$this->config->idColumn] = Dce::$config->idGenerator->generate($this->config->idTag, $upShardingValue, $this->config->shardingTag);
                        } else { // 否则抛出需指定ID的异常
                            throw (new MiddlewareException(MiddlewareException::UP_ID_VALUE_NOT_SPECIFIED))->format($this->config->tableName, $this->config->idColumn);
                        }
                    }
                    $upIdValue && null === $upShardingValue
                        && throw (new MiddlewareException(MiddlewareException::UP_SHARDING_VALUE_NOT_SPECIFIED))->format($this->config->tableName, $this->config->idColumn);
                }

                // 记录需要移动插入到新库的数据
                $insertDataMapping[$upDbAlias][] = $insData;
                // 记录需要删除的记录ID (若在分库配置中未配置ID, 则会以分库字段作为待删数据的筛选条件)
                $deleteIdsMapping[$oldDbAlias][$oldValue] ??= 1;
            }
        }
        $dbMapping = [];
        foreach ($insertDataMapping as $dbAlias => $insertData) {
            // 向新库迁移插入数据
            $dbMapping[$dbAlias][] = $this->directiveParser->buildInsert($insertData);
        }
        foreach ($deleteIdsMapping as $dbAlias => $deleteIdKeySet) {
            // 将旧库的已迁移数据删除
            // buildDelete里面已经自动拼装了主体语句的where条件，无需在此处处理
            $dbMapping[$dbAlias][] = $this->directiveParser->buildDelete([$upValueColumn, 'in', array_keys($deleteIdKeySet)]);
        }
        return $dbMapping;
    }

    /**
     * 处理待储存数据, 根据ID划分库并将分组的数据与其绑定
     * @param array $storeData
     * @return array
     * @throws MiddlewareException
     * @throws QueryException
     * @throws IdgException
     */
    private function navigationByStoreData(array $storeData): array {
        $dbStoreData = [];
        foreach ($storeData as $datum) {
            // 如果已配置idColumn且未传ID，则尝试自动生成
            if ($this->config->idColumn && ! isset($datum[$this->config->idColumn])) {
                ! $this->config->idTag && throw (new MiddlewareException(MiddlewareException::INSERT_ID_NOT_SPECIFIED))->format($this->config->idColumn);
                if ($this->config->shardingColumn) {
                    ! isset($datum[$this->config->shardingColumn]) && throw (new MiddlewareException(MiddlewareException::GENE_COLUMN_NOT_FOUND))->format($this->config->shardingColumn);
                    $datum[$this->config->idColumn] = $this->lastInsertId = Dce::$config->idGenerator->generate($this->config->idTag, $datum[$this->config->shardingColumn], $this->config->shardingTag);
                } else {
                    $datum[$this->config->idColumn] = $this->lastInsertId = Dce::$config->idGenerator->generate($this->config->idTag);
                }
            }

            $shardingValue = $datum[$this->config->shardingIdColumn] ?? null;
            null === $shardingValue && throw (new MiddlewareException(MiddlewareException::SHARDING_COLUMN_NOT_SPECIFIED))->format($this->config->shardingIdColumn);
            $dbAlias = null;
            if ($this->config->isModulo()) {
                $remainder = Dce::$config->idGenerator->mod($this->config->modulus, $shardingValue, $this->config->shardingIdTag);
                $dbAlias = $this->config->flipMapping[$remainder] ?? null;
            } else if ($this->config->isRange()) {
                $dbAlias = self::rangeMappingEqual($this->config->flipMapping, $shardingValue)[0] ?? null;
            }
            ! $dbAlias && throw (new QueryException(QueryException::ID_CANNOT_MATCH_DB))->format($shardingValue);
            $dbStoreData[$dbAlias][] = $datum;
        }

        return $dbStoreData;
    }

    /**
     * 根据筛选条件定位并列出结果记录所在的分库名
     * @param array $conditions
     * @return array
     * @throws IdgException
     */
    private function navigationByCondition(array $conditions): array {
        $dbSet = [];
        $logic = null;
        foreach ($conditions as $condition) {
            if (is_string($condition)) {
                $logic = $condition;
            } else {
                $subDbSet = [];
                if (is_array($condition)) {
                    $subDbSet = $this->navigationByCondition($condition);
                } else if ($condition instanceof WhereConditionSchema) {
                    // 如果当前字段为分库依据字段, 则尝试根据筛选值定位数据库集
                    if (in_array($condition->columnPure, [$this->config->idColumn, $this->config->shardingColumn])) {
                        $subDbSet = $this->locatingDbByCondition($condition, $condition->columnPure == $this->config->idColumn ? $this->config->idTag : $this->config->shardingTag);
                    }
                }
                if ($subDbSet) {
                    // 如果当前条件定位到了数据库
                    if (null === $logic) { // 如果为第一个条件, 则直接赋值, 不作交并计算
                        $dbSet = $subDbSet;
                    } else if ('OR' === $logic) { // OR则为并集
                        $dbSet = array_unique(array_merge($dbSet, $subDbSet));
                    } else { // AND为交集
                        $dbSet = $dbSet ? array_intersect($dbSet, $subDbSet) : $subDbSet;
                    }
                } else if ('OR' === $logic) {
                    // 如果当前条件非分库条件, 且逻辑为或, 则表示无法利用分库字段直接定位到具体分库来查询, 可以直接返回为空条件组
                    return [];
                }
            }
        }
        return $dbSet;
    }

    /**
     * 根据分库条件定位目标分库
     * @param WhereConditionSchema $condition
     * @param string|null $shardingTag
     * @return array
     * @throws IdgException
     */
    private function locatingDbByCondition(WhereConditionSchema $condition, string|null $shardingTag): array {
        $values = $condition->value;
        $dbSet = [];
        $mapping = $this->config->flipMapping;
        if ($this->config->isModulo()) {
            switch ($condition->operator) {
                case '=':
                    $values = [$values];
                case 'IN':
                    foreach ($values as $value) {
                        $remainder = Dce::$config->idGenerator->mod($this->config->modulus, $value, $shardingTag);
                        $dbName = $mapping[$remainder] ?? null;
                        if (null === $dbName) { // 如果某个值无法定位到库, 则实为无效值, 则应干脆不以该组不可靠的值去定位目标库
                            $dbSet = [];
                            break;
                        } else if (! in_array($dbName, $dbSet)) {
                            $dbSet[] = $dbName;
                        }
                    }
            }
        } else if ($this->config->isRange()) {
            switch ($condition->operator) {
                case '=':
                    $values = [$values];
                case 'IN':
                    foreach ($values as $value) {
                        $dbSet = array_merge($dbSet, self::rangeMappingEqual($mapping, $value));
                    }
                    $dbSet = array_unique($dbSet);
                    break;
                case '>':
                case '>=':
                    $dbSet = self::rangeMappingGreater($mapping, $values, $condition->operator === '>=');
                    break;
                case '<':
                case '<=':
                    $dbSet = self::rangeMappingLess($mapping, $values, $condition->operator === '<=');
                    break;
                case 'BETWEEN':
                    // BETWEEN就是>=与<=的交集
                    $dbSet = self::rangeMappingGreater($mapping, $values[0], true);
                    $dbSet = array_intersect($dbSet, self::rangeMappingLess($mapping, $values[1], true));
            }
        }
        return $dbSet;
    }

    /**
     * 以"等号比较值"定位区间映射数据库
     * @param array $mapping
     * @param float $value
     * @return array
     */
    private static function rangeMappingEqual(array $mapping, float $value): array {
        $dbLocated = null;
        foreach ($mapping as $threshold => $dbName) {
            $dbLocated = $dbName;
            if ($value >= $threshold) {
                break;
            }
        }
        return $dbLocated ? [$dbLocated] : [];
    }

    /**
     * 以"大于号比较值"定位区间映射数据库
     * @param array $mapping
     * @param float $value
     * @param bool $geq
     * @return array
     */
    private static function rangeMappingGreater(array $mapping, float $value, bool $geq = false): array {
        $dbSet = [];
        $offset = $geq ? 0 : -1;
        foreach ($mapping as $threshold => $dbName) {
            $dbSet[] = $dbName;
            if ($value - $offset >= $threshold) {
                break;
            }
        }
        return $dbSet;
    }

    /**
     * 以"小于号比较值"定位区间映射数据库
     * @param array $mapping
     * @param float $value
     * @param bool $leq
     * @return array
     */
    private static function rangeMappingLess(array $mapping, float $value, bool $leq = false): array {
        $offset = $leq ? 0 : 1;
        $outRangeSet = [];
        foreach ($mapping as $threshold => $dbName) {
            if ($value - $offset < $threshold) {
                $outRangeSet[] = $dbName;
            } else {
                break;
            }
        }
        // 如果小于比较值无法定位到库, 则默认到最小值区间库
        return array_diff($mapping, $outRangeSet) ?: [array_pop($outRangeSet)];
    }


    /**
     * 惰性获取单例数据处理器
     * @return DbReadProcessor|DbWriteProcessor
     */
    public function getProcessor(): DbReadProcessor|DbWriteProcessor {
        ! isset($this->processor)
            && $this->processor = $this->directiveParser->isSelect() ? new DbReadProcessor($this->directiveParser) : new DbWriteProcessor();
        return $this->processor;
    }

    public function queryAll(string|null $indexColumn = null, string|null $extractColumn = null): array {
        return $this->directiveParser->isSharding()
            ? $this->getProcessor()->queryAll($indexColumn, $extractColumn)
            : $this->retryableQuery(fn($connector) => $connector->queryAll($this->directiveParser->getStatement(), $indexColumn, $extractColumn));
    }

    public function queryEach(Closure|null $decorator = null): Iterator {
        return $this->directiveParser->isSharding()
            ? $this->getProcessor()->queryEach($decorator)
            : $this->retryableQuery(fn($connector) => $connector->queryEach($this->directiveParser->getStatement(), $decorator));
    }

    public function queryOne(): array|false {
        return $this->directiveParser->isSharding()
            ? $this->getProcessor()->queryOne()
            : $this->retryableQuery(fn($connector) => $connector->queryOne($this->directiveParser->getStatement()));
    }

    public function queryColumn(): string|float|null|false {
        return $this->directiveParser->isSharding()
            ? $this->getProcessor()->queryColumn()
            : $this->retryableQuery(fn($connector) => $connector->queryColumn($this->directiveParser->getStatement()));
    }

    public function queryGetInsertId(): int|string {
        return $this->directiveParser->isSharding()
            ? $this->getProcessor()->queryGetInsertId()
            : $this->retryableQuery(fn($connector) => $connector->queryGetInsertId($this->directiveParser->getStatement()));
    }

    public function queryGetAffectedCount(): int {
        return $this->directiveParser->isSharding()
            ? $this->getProcessor()->queryGetAffectedCount()
            : $this->retryableQuery(fn($connector) => $connector->queryGetAffectedCount($this->directiveParser->getStatement()));
    }

    public function query(array $fetchArgs): array {
        return $this->retryableQuery(fn($connector) => $connector->query($this->directiveParser->getStatement(), $this->directiveParser->getParams(), $fetchArgs));
    }

    public function execute(): int|string {
        return $this->retryableQuery(fn($connector) => $connector->execute($this->directiveParser->getStatement(), $this->directiveParser->getParams()));
    }

    /**
     * 在自动重连连接池容器中执行查询
     * @param callable<DbConnector, mixed> $query
     * @return mixed
     * @throws Exception|QueryException
     */
    private function retryableQuery(callable $query): mixed {
        $data = null;
        $nonTransConnector = null;
        $exceptions = [];
        $dbConfigs = Dce::$config->mysql->getConfig($this->dbProxy->dbAlias, $this->directiveParser->isWrite());
        $connectorPool = DbPool::inst($this->dbProxy->dbAlias, $this->directiveParser->isWrite())->setConfigs($dbConfigs, false);
        $barrier = SwooleUtility::inSwoole() ? Barrier::make() : null;
        // 注册协程自动释放，解决未知原因导致长时间闲置连接导致断开时，PDO::prepare()可能需等待960秒才能释放协程抛出连接已断开的异常的问题
        PdoDbConnector::registerCoroutineAutoReleaseOrHandle(
            $connectorPool->retryableContainer(function() use($query, $connectorPool, &$data, &$nonTransConnector) {
                // 若打开了事务开关, 则尝试开启事务
                $transaction = ShardingTransaction::tryBegin(ShardingTransaction::ALIAS_NO_SHARDING, $this->dbProxy->dbAlias, $connectorPool);
                $inTransaction = $transaction instanceof ShardingTransaction;
                $connector = $inTransaction ? $transaction->getConnector() : $transaction;
                ! $inTransaction && $nonTransConnector = $connector;
                $data = call_user_func($query, $connector);
            }, $exceptions, $barrier)
        );
        $barrier && Barrier::wait($barrier);
        $exceptions && throw array_pop($exceptions);
        // 若连接未绑定到事务中，则还给连接池
        $nonTransConnector && $connectorPool->put($nonTransConnector);
        return $data;
    }
}
