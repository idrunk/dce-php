<?php
/**
 * Author: Drunk
 * Date: 2020-05-14 15:41
 */

namespace dce\service\extender;

use dce\config\ConfigManager;
use dce\db\connector\DbConfig;
use dce\db\connector\PdoDbConnector;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\schema\InsertSchema;
use dce\db\query\builder\schema\TableSchema;
use dce\db\query\builder\Statement\InsertStatement;
use dce\Dce;
use drunk\Structure;
use Swoole\Coroutine\WaitGroup;

/**
 * Class MysqlHashExtender
 * @package db\service\extender
 * @property PdoDbConnector[] $connections
 */
class MysqlModuloExtender extends ModuloExtender {
    protected string $dbType = 'mysql';

    private array $tableNamesTransferred = [];

    /**
     * MysqlModuloExtender constructor.
     */
    protected function __construct() {
        $this->shardingConfigs = Dce::$config->sharding->filter(['type' => $this->shardingType, 'dbType' => $this->dbType]);
        $this->srcDatabases = Dce::$config->mysql->filter();

        $this->extendConfig = Dce::$config->shardingExtend;
        $this->extendDatabases = DbConfig::load($this->extendConfig['database'])->filter();
        $this->extendMappings = $this->extendConfig['mapping'];
    }

    /** @inheritDoc */
    protected function checkExtendConfig(): bool {
        // 遍历分库规则数据表配置集
        foreach ($this->shardingConfigs as $tableName => $shardingConfig) {
            if (! isset($this->extendMappings[$shardingConfig->alias])) {
                unset($this->shardingConfigs[$tableName]);
                continue;
            }
            $extendMapping = $this->extendMappings[$shardingConfig->alias];
            $shardingMapping = $shardingConfig->mapping;
            $srcRuleCount = count($shardingMapping);
            $mapping = array_merge($shardingMapping, $extendMapping);
            $ruleModel = $shardingConfig->targetModulus = count($mapping);
            $shardingConfig->targetMapping = $mapping;
            if ($ruleModel % $srcRuleCount) {
                // 如果没有扩展配置, 或扩展配置非源配置的正数倍, 则表示配置异常
                throw new ExtenderException(ExtenderException::INVALID_MAPPING_CONFIG);
            }
            for (; -- $ruleModel;) {
                if (! in_array($ruleModel, $mapping)) {
                    // 如果该有的模值在映射中找不到, 则表示配置错误
                    throw (new ExtenderException(ExtenderException::MAPPING_CONFIG_MISSING_PROPERTY))->format($ruleModel);
                }
            }
            foreach ($extendMapping as $dbAlias => $remainder) {
                if (! key_exists($dbAlias, $this->extendDatabases)) {
                    throw (new ExtenderException(ExtenderException::DATABASE_MAPPING_NOT_MATCH))->format($shardingConfig->alias);
                }
                foreach ($shardingMapping as $srcDbAlias => $srcRemainder) {
                    if ($remainder % $srcRuleCount === $srcRemainder % $srcRuleCount) {
                        // 拼一个源表拓展表映射规则, 如['extend_mapping' => ['222:3306' => ['remainder' => 0,'extends' => ['mysql:2333' => 4,'mysql:2333' => 8,],],],]
                        $shardingConfig->extendMapping[$srcDbAlias]['remainder'] = $srcRemainder;
                        $shardingConfig->extendMapping[$srcDbAlias]['extends'][$remainder] = $dbAlias;
                    }
                }
            }
            $this->shardingConfigs[$tableName] = $shardingConfig;
        }
        return true;
    }

    /** @inheritDoc */
    protected function dbsExists(): bool {
        $connections = [];
        // 连接到源库
        foreach ($this->srcDatabases as $dbAlias => [$database]) {
            $connector = new PdoDbConnector();
            $connector->connect($database->dbName, $database->host, $database->dbUser, $database->dbPassword, $database->dbPort, false);
            $connections[$dbAlias] = $connector;
        }
        // 连接到扩展库
        foreach ($this->extendDatabases as $dbAlias => [$database]) {
            $connector = new PdoDbConnector();
            $connector->connect($database->dbName, $database->host, $database->dbUser, $database->dbPassword, $database->dbPort, false);
            $connections[$dbAlias] = $connector;
        }
        $this->connections = $connections;
        return true;
    }

    /** @inheritDoc */
    protected function createExtendTable(): bool {
        // 遍历分库规则数据表配置集
        foreach ($this->shardingConfigs as $tableName => $shardingConfig) {
            // 遍历源库及其拓展库的映射
            foreach ($shardingConfig->extendMapping as $srcDbAlias => ['extends' => $extendMapping]) {
                $sqlCreateTable = $this->connections[$srcDbAlias]->queryColumn(new RawBuilder("SHOW CREATE TABLE `{$tableName}`", false), 1);
                $sqlCreateTable = str_ireplace("CREATE TABLE", "CREATE TABLE IF NOT EXISTS", $sqlCreateTable);
                // 遍历拓展库, 准备建表
                foreach ($extendMapping as $dbAlias) {
                    $this->connections[$dbAlias]->execute($sqlCreateTable, []);
                }
            }
        }
        return true;
    }

    /** @inheritDoc */
    protected function insertIntoExtend(): bool {
        $perTransfer = $this->extendConfig['volume_per_transfer'] ?? 2000;
        // 遍历分库规则数据表配置集
        $wasDone = true;
        $batchLogs = [];
        foreach ($this->shardingConfigs as $tableName => $shardingConfig) {
            if (in_array($tableName, $this->tableNamesTransferred)) {
                // 跳过已经迁移完毕的表
                continue;
            }
            $idColumns = $this->getIdColumns(current(array_keys($shardingConfig->extendMapping)), $tableName);
            $sqlOrderBy = implode(',', array_map(fn($column) => "`{$column}`", $idColumns));
            $waitGroup = new WaitGroup();
            // 遍历源库及其拓展库的映射
            foreach ($shardingConfig->extendMapping as $srcDbAlias => ['extends' => $extendMapping]) {
                $maxId = $this->getMaxExtendId($extendMapping, $tableName, $idColumns);
                $sqlWhere = $this->getMaxWhereCondition($maxId);
                $waitGroup->add();
                go(function () use($waitGroup, $perTransfer, & $wasDone, & $batchLogs, $shardingConfig, $sqlWhere, $extendMapping, $srcDbAlias, $tableName, $sqlOrderBy) {
                    $limit = $perTransfer + $perTransfer * count($extendMapping);
                    // 取出批次可能需要迁移的源数据
                    $logs = $this->connections[$srcDbAlias]->queryAll(new RawBuilder("SELECT * FROM `{$tableName}` WHERE {$sqlWhere} ORDER BY {$sqlOrderBy} LIMIT {$limit}"));
                    if ($logs) {
                        $logsCount = count($logs);
                        $this->print("从源表{$srcDbAlias}.{$tableName}取出了{$logsCount}条待处理数据...");
                        if ($logsCount >= $limit) {
                            // 如果取到的记录数等于查询量, 则表示可能还有待迁记录
                            $wasDone = false;
                        } else {
                            // 记录已迁移完毕的, 待下个迭代迁移时跳过这些表
                            $this->tableNamesTransferred[] = $tableName;
                        }
                        foreach ($logs as $log) {
                            // 提取出排除掉服务ID后的可mod的ID并mod取余
                            $remainder = Dce::$config->idGenerator->mod($shardingConfig->targetModulus, $log[$shardingConfig->shardingIdColumn], $shardingConfig->shardingIdTag);
                            // 根据余数定位目标库并将记录与之mapping
                            if (key_exists($remainder, $extendMapping)) {
                                $targetDbId = $extendMapping[$remainder];
                                $batchLogs[$tableName][$targetDbId][] = $log;
                            }
                        }
                    }
                    $waitGroup->done();
                });
            }
            $waitGroup->wait();
        }
        $this->insertIgnoreInto($batchLogs);
        return $wasDone;
    }

    /**
     * 将待迁移数据批量插入到扩展库
     * @param array $batchLogs
     */
    private function insertIgnoreInto(array $batchLogs): void {
        foreach ($batchLogs as $tableName => $dbLogs) {
            $waitGroup = new WaitGroup();
            foreach ($dbLogs as $dbAlias => $logs) {
                $waitGroup->add();
                go(function() use($waitGroup, $dbAlias, $tableName, $logs) {
                    $insertStatement = new InsertStatement((new TableSchema())->addTable($tableName, null), new InsertSchema($logs), true);
                    $cntInserted = $this->connections[$dbAlias]->queryGetAffectedCount($insertStatement);
                    $this->print("成功向{$dbAlias}.{$tableName}插入了{$cntInserted}条数据");
                    $waitGroup->done();
                });
            }
            $waitGroup->wait();
            unset($batchLogs[$tableName]);
        }
    }

    /**
     * 查询表的主键字段集
     * @param string $dbAlias
     * @param string $tableName
     * @return array
     */
    private function getIdColumns(string $dbAlias, string $tableName): array {
        $columns = $this->connections[$dbAlias]->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'", [], []);
        $indexes = array_column($columns, 'Seq_in_index');
        $primaries = array_column($columns, 'Column_name');
        array_multisort($indexes, SORT_ASC, SORT_NUMERIC, $primaries);
        return $primaries;
    }

    /**
     * 从扩展库中查找当前最大的主键, (供对比查出新的待迁移的源数据)
     * @param array $dbIds
     * @param string $tableName
     * @param array $idColumns
     * @return array
     */
    private function getMaxExtendId(array $dbIds, string $tableName, array $idColumns): array {
        $sqlOrderBy = implode(',', array_map(fn($column) => "`{$column}` DESC", $idColumns));
        $sqlColumns = implode(',', array_map(fn($column) => "`{$column}`", $idColumns));
        $maxLogs[] = array_map(fn() => 0, array_flip($idColumns));
        foreach ($dbIds as $dbAlias) {
            $maxLog = $this->connections[$dbAlias]->queryOne(new RawBuilder("SELECT {$sqlColumns} FROM `{$tableName}` ORDER BY {$sqlOrderBy} LIMIT 1")) ?? null;
            if ($maxLog) {
                $maxLogs[] = $maxLog;
            }
        }
        $orderBys = [];
        foreach ($idColumns as $column) {
            $orderBys = [... $orderBys, array_column($maxLogs, $column), SORT_DESC, SORT_NUMERIC];
        }
        $orderBys[] = & $maxLogs;
        // 将主键逆序排序, 取第一个为最大主键
        array_multisort(... $orderBys);
        return current($maxLogs);
    }

    /**
     * 拼装筛选待迁移的扩展数据的Sql查询条件, 如: getMaxWhereCondition(['mid' => 100, 'bid' => 200]) => (`mid` > 100 OR (`mid` = 100 AND `bid` > 200))
     * @param array $maxId
     * @return string
     */
    private function getMaxWhereCondition(array $maxId): string {
        $index = 0;
        $count = count($maxId);
        $idColumns = array_keys($maxId);
        $conditionSqls = [];
        foreach ($maxId as $column => $value) {
            $condition = [];
            for ($i = 0; $i < $index; $i ++) {
                $preColumn = $idColumns[$i];
                $preValue = $maxId[$preColumn];
                $condition[] = "`{$preColumn}` = " . (is_numeric($preValue) ? $preValue : "'{$preValue}'");
            }
            $condition[] = "`{$column}` > " . (is_numeric($value) ? $value : "'{$value}'");
            $conditionSql = implode(" AND ", $condition);
            if ($i > 0) {
                $conditionSql = "($conditionSql)";
            }
            $conditionSqls[] = $conditionSql;
            $index ++;
        }
        $fullSql = implode(" OR ", $conditionSqls);
        if ($count > 1) {
            $fullSql = "($fullSql)";
        }
        return $fullSql;
    }

    /** @inheritDoc */
    protected function applyExtendConfig(): bool {
        $input = strtolower($this->input("请应用扩展配置, 应用完毕输入yes并回车继续: "));
        if ('yes' !== $input) {
            // 不是yes继续输入
            $this->applyExtendConfig();
        }
        // 重置已迁移容器, 供后续继续迁移新配生效前的可能的源表新增记录
        $this->tableNamesTransferred = [];
        return true;
    }

    /** @inheritDoc */
    protected function checkApplyExtendConfig(): bool {
        $newConfig = ConfigManager::newCommonConfig();
        $newShardingConfigs = $newConfig->sharding->filter(['type' => $this->shardingType, 'dbType' => $this->dbType]);
        foreach ($this->shardingConfigs as $tableName => $shardingConfig) {
            if ($shardingConfig->targetModulus !== ($newShardingConfigs[$tableName]->modulus ?? 0)) {
                // 如果目标模数与新配置模数不等, 则表示未应用新配
                return false;
            }
            if (false === Structure::arraySearchMatrix($shardingConfig->targetMapping, [$newShardingConfigs[$tableName]->mapping ?? []])) {
                // 如果目标映射与新配置映射不等, 则表示未应用新配
                return false;
            }
        }
        return true;
    }

    /** @inheritDoc */
    protected function hashClear(): bool {
        // 遍历分库规则数据表配置集
        foreach ($this->shardingConfigs as $tableName => $shardingConfig) {
            $serverBitWidth = $shardingConfig->shardingIdTag ? Dce::$config->idGenerator->getClient($shardingConfig->shardingIdTag)->getBatch($shardingConfig->shardingIdTag)->serverBitWidth : null;
            $divisorSql = null === $serverBitWidth ? "crc32(`$shardingConfig->shardingIdColumn`)" : "`$shardingConfig->shardingIdColumn` >> $serverBitWidth";
            // 遍历源库及其拓展库的映射
            $waitGroup = new WaitGroup();
            foreach ($shardingConfig->targetMapping as $dbAlias => $remainder) {
                $waitGroup->add();
                go(function () use($waitGroup, $dbAlias, $tableName, $divisorSql, $shardingConfig, $remainder) {
                    // 删除源库中已被迁移到新库的记录 (即按模取余非0的记录)
                    $cntDeleted = $this->connections[$dbAlias]->execute("DELETE FROM `$tableName` WHERE ($divisorSql) % $shardingConfig->targetModulus > $remainder", []);
                    $this->print("成功从{$dbAlias}.{$tableName}删除了{$cntDeleted}条拓表冗余数据");
                    $waitGroup->done();
                });
            }
            $waitGroup->wait();
        }
        return true;
    }
}
