<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/10/7 13:02
 */

namespace dce\db\proxy;

use Closure;
use dce\db\connector\DbConfig;
use dce\db\connector\DbConnector;
use dce\db\connector\PdoDbConnector;
use dce\db\Query;
use dce\db\query\builder\StatementAbstract;
use dce\Dce;
use drunk\Utility;
use Iterator;
use PDO;

final class SimpleDbProxy extends DbProxy {
    private function __construct(
        private DbConfig $config,
    ) {}

    /**
     * 取一个指定库的代理实例, 若指定库未实例化过则实例化一个新的再返回, 否则直接返回
     * @param string|null $dbAlias
     * @param bool $useDb
     * @return self
     */
    public static function inst(string|null $dbAlias = null, bool $useDb = true): static {
        $dbAlias ??= DbConfig::DEFAULT_DB;
        static $mapping = [];
        ! key_exists($dbAlias, $mapping) && $mapping[$dbAlias] = self::new($dbAlias, $useDb);
        return $mapping[$dbAlias];
    }

    /**
     * 实例化一个新的对象
     * @param string $dbAlias
     * @param bool $useDb
     * @return static
     */
    public static function new(string $dbAlias, bool $useDb = true): self {
        $config = clone self::getDbConfig($dbAlias);
        ! $useDb && $config->dbName = null;
        return new self($config);
    }

    /**
     * 取库配置, 若未指定库, 则使用默认库
     * @param string $dbAlias
     * @return DbConfig
     */
    private static function getDbConfig(string $dbAlias): DbConfig {
        $config = Dce::$config->mysql->getConfig($dbAlias, true);
        // 非分库配置, 无视主从库冗余库等, 取第一个主库配置即可
        Utility::isArrayLike($config[0] ?? 0) && $config = $config[0];
        return $config;
    }

    /**
     * 取可复用的连接器
     * @param StatementAbstract|string|null $statement
     * @param array|null $params
     * @return DbConnector
     */
    private function getConnector(StatementAbstract|string $statement = null, array|null $params = null): DbConnector {
        static $connectorMapping = [];
        $hostKey = $this->config->host .':'. $this->config->dbPort;
        if (! key_exists($hostKey, $connectorMapping)) {
            $connectorMapping[$hostKey] = new PdoDbConnector();
            $connectorMapping[$hostKey]->connect($this->config->dbName, $this->config->host, $this->config->dbUser, $this->config->dbPassword, $this->config->dbPort);
        }
        $statement && $this->logStatement($statement, $params);
        // 尝试开启事务
        SimpleTransaction::tryBegin($this, $connectorMapping[$hostKey]);
        return $connectorMapping[$hostKey];
    }

    public function getConnection(): PDO {
        return $this->getConnector()->getConnection();
    }

    /** @inheritDoc */
    public function queryAll(StatementAbstract $statement, string|null $indexColumn = null, string|null $extractColumn = null): array {
        return $this->getConnector($statement, $statement->getParams())->queryAll($statement, $indexColumn, $extractColumn);
    }

    /** @inheritDoc */
    public function queryEach(StatementAbstract $statement, Closure|null $callback = null): Iterator {
        return $this->getConnector($statement, $statement->getParams())->queryEach($statement, $callback);
    }

    /** @inheritDoc */
    public function queryOne(StatementAbstract $statement): array|false {
        return $this->getConnector($statement, $statement->getParams())->queryOne($statement);
    }

    /** @inheritDoc */
    public function queryColumn(StatementAbstract $statement, int $column = 0): string|int|float|null|false {
        return $this->getConnector($statement, $statement->getParams())->queryColumn($statement, $column);
    }

    /** @inheritDoc */
    public function queryGetAffectedCount(StatementAbstract $statement): int {
        return $this->getConnector($statement, $statement->getParams())->queryGetAffectedCount($statement);
    }

    /** @inheritDoc */
    public function queryGetInsertId(StatementAbstract $statement): int|string {
        return $this->getConnector($statement, $statement->getParams())->queryGetInsertId($statement);
    }

    /** @inheritDoc */
    public function query(string $statement, array $params, array $fetchArgs): array {
        return $this->getConnector($statement, $params)->query($statement, $params, $fetchArgs);
    }

    /** @inheritDoc */
    public function execute(string $statement, array $params): int|string {
        return $this->getConnector($statement, $params)->execute($statement, $params);
    }

    /** @inheritDoc */
    public function begin(Query $query): Transaction {
        // ProxySimple对象能经过Proxy类型约束也能再经过ProxySimple类型约束
        return SimpleTransaction::begin($query->getProxy(null));
    }
}
