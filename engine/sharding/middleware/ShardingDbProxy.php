<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/10/7 13:01
 */

namespace dce\sharding\middleware;

use Closure;
use dce\db\connector\DbConfig;
use dce\db\proxy\DbProxy;
use dce\db\proxy\Transaction;
use dce\db\Query;
use dce\db\query\builder\StatementInterface;
use dce\Dce;
use Iterator;

final class ShardingDbProxy extends DbProxy {
    private function __construct(
        public string $dbAlias,
    ) {}

    /**
     * 取一个指定库的单例代理对象
     * @param string|null $dbAlias
     * @return static
     */
    public static function inst(string|null $dbAlias = null): self {
        $dbAlias ??= DbConfig::DEFAULT_DB;
        static $mapping = [];
        ! key_exists($dbAlias, $mapping) && $mapping[$dbAlias] = new self($dbAlias);
        return $mapping[$dbAlias];
    }

    /**
     * 取一个新的分库中间件实例
     * @param StatementInterface|string $statement
     * @param array $params
     * @return DbMiddleware
     * @throws MiddlewareException
     */
    private function getMiddleware(StatementInterface|string $statement, array $params): DbMiddleware {
        $this->logStatement($statement, $params);
        $directiveParser = new DbDirectiveParser($statement, $params);
        return new DbMiddleware($directiveParser, $this);
    }

    /** @inheritDoc */
    public function queryAll(StatementInterface $statement, string|null $indexColumn = null, string|null $extractColumn = null): array {
        return $this->getMiddleware($statement, $statement->getParams())->queryAll($indexColumn, $extractColumn);
    }

    /** @inheritDoc */
    public function queryEach(StatementInterface $statement, Closure|null $decorator = null): Iterator {
        return $this->getMiddleware($statement, $statement->getParams())->queryEach($decorator);
    }

    /** @inheritDoc */
    public function queryOne(StatementInterface $statement): array|false {
        return $this->getMiddleware($statement, $statement->getParams())->queryOne();
    }

    /** @inheritDoc */
    public function queryColumn(StatementInterface $statement): string|int|float|null|false {
        return $this->getMiddleware($statement, $statement->getParams())->queryColumn();
    }

    /** @inheritDoc */
    public function queryGetAffectedCount(StatementInterface $statement): int {
        return $this->getMiddleware($statement, $statement->getParams())->queryGetAffectedCount();
    }

    /** @inheritDoc */
    public function queryGetInsertId(StatementInterface $statement): int|string {
        return $this->getMiddleware($statement, $statement->getParams())->queryGetInsertId();
    }

    /** @inheritDoc */
    public function query(string $statement, array $params, array $fetchArgs): array {
        return $this->getMiddleware($statement, $params)->query($fetchArgs);
    }

    /** @inheritDoc */
    public function execute(string $statement, array $params): int|string {
        return $this->getMiddleware($statement, $params)->execute();
    }

    /** @inheritDoc */
    public function begin(Query $query): Transaction {
        $tableName = $query->getQueryBuilder()->getTableSchema()->getName();
        $shardingConfig = Dce::$config->sharding->getConfig($tableName);
        return new ShardingTransaction($shardingConfig->alias ?? ShardingTransaction::ALIAS_NO_SHARDING);
    }
}
