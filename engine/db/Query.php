<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/7/11 11:32
 */

namespace dce\db;

use Closure;
use dce\base\SwooleUtility;
use dce\db\proxy\DbProxy;
use dce\db\proxy\SimpleDbProxy;
use dce\db\proxy\Transaction;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\SchemaAbstract;
use dce\db\query\builder\Statement\SelectStatement;
use dce\db\query\builder\StatementAbstract;
use dce\db\query\QueryBuilder;
use dce\sharding\middleware\ShardingDbProxy;
use Iterator;
use PDO;

/**
 * 代理查询类, 主要做一些配置读取与设置, 及实体选择与实例化的工作
 * @package dce\db
 */
class Query {
    private QueryBuilder $queryBuilder;

    private DbProxy $proxy;

    /**
     * Query constructor.
     * @param null|string|DbProxy $proxy 代理实例或者指定库名
     */
    public function __construct(null|string|DbProxy $proxy = null) {
        $this->getProxy($proxy);
        $this->queryBuilder = new QueryBuilder();
    }

    /**
     * 取代理, 若实例化时未指定代理入参, 则尝试取默认代理, 若未指定默认代理, 则会自动实例化 ProxyDefault 作为驱动代理
     * @param null|string|DbProxy $proxy 代理实例
     * @return DbProxy
     */
    public function getProxy(null|string|DbProxy $proxy): DbProxy {
        if (! isset($this->proxy)) {
            if ($proxy instanceof DbProxy) {
                $this->proxy = $proxy;
            } else if ($proxy) {
                $this->proxy = SwooleUtility::inSwoole() ? ShardingDbProxy::inst($proxy) : SimpleDbProxy::inst($proxy);
            } else {
                $this->proxy = SwooleUtility::inSwoole() ? ShardingDbProxy::inst() : SimpleDbProxy::inst();
            }
        }
        return $this->proxy;
    }

    /**
     * 取查询构造器, 供外部使用
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder {
        return $this->queryBuilder;
    }

    /**
     * 指定待查数据表
     * @param string|RawBuilder|SelectStatement $tableName 表名
     * <pre>
     * string, 普通数据表表名
     * RawBuilder, 可以为普通表名, 也可以用sql语句子查询作为临时表
     * SelectStatement, 查询实例, 子查询作为临时表
     * </pre>
     * @param string|null $alias 设置表别名
     * @return $this
     */
    public function table(string|RawBuilder|SelectStatement $tableName, string|null $alias = null): self {
        $this->queryBuilder->addTable($tableName, $alias);
        return $this;
    }

    /**
     * 内连接查询
     * @param string|RawBuilder|SelectStatement $tableName 表名
     * <pre>
     * string, 普通数据表表名
     * RawBuilder, 可以为普通表名, 也可以用sql语句子查询作为临时表
     * SelectStatement, 查询实例, 子查询作为临时表
     * </pre>
     * @param string|null $alias 设置表别名
     * @param array|string|RawBuilder|WhereSchema $on 连接条件
     * <pre>
     * array, 数组式where条件, 如, ['t2.mid', '=', 't1.mid'], 更多说明可参见where注解
     * string, 字符串式条件, 如, 't2.mid=t1.mid', (注意, 系统会自动用RawBuilder包裹, 因为on实际如where参数, 会交给WhereSchema处理, 所以为了用户方便, 自动包裹以通过校验)
     * RawBuilder, 原始语句条件, 如, raw('t2.mid=t1.mid'), (您也可以直接用字符串, 最终会自动包裹为RawBuilder, 但请不要以接收的用户入参作为on参数, 防止用户的非法注入)
     * WhereSchema, 实例化实体where条件
     * mixed, 其他, 如 1 之类的, 不建议
     * </pre>
     * @return $this
     */
    public function join(string|RawBuilder|SelectStatement $tableName, string|null $alias, string|array|RawBuilder|WhereSchema $on): self {
        $this->queryBuilder->addJoin($tableName, $alias, $on, 'INNER');
        return $this;
    }

    /**
     * 左连接查询, 入参详情参见 "内连接查询"
     * @param string|RawBuilder|SelectStatement $tableName
     * @param string|null $alias
     * @param array|string|RawBuilder|WhereSchema $on
     * @return $this
     */
    public function leftJoin(string|RawBuilder|SelectStatement $tableName, string|null $alias, array|string|RawBuilder|WhereSchema $on): self {
        $this->queryBuilder->addJoin($tableName, $alias, $on, 'LEFT');
        return $this;
    }

    /**
     * 右连接查询, 入参详情参见 "内连接查询"
     * @param string|RawBuilder|SelectStatement $tableName
     * @param string|null $alias
     * @param array|string|RawBuilder|WhereSchema $on
     * @return $this
     */
    public function rightJoin(string|RawBuilder|SelectStatement $tableName, string|null $alias, array|string|RawBuilder|WhereSchema $on): self {
        $this->queryBuilder->addJoin($tableName, $alias, $on, 'RIGHT');
        return $this;
    }

    /**
     * 添加查询条件(AND逻辑)
     * @param string|array|RawBuilder|WhereSchema $columnName 条件字段名 或 查询条件
     * <pre>
     * string, 条件字段名, (最终会结合 $operator 与 $value 组装为array式条件)
     * array, 查询条件, (按实际需求灵活搭配, 任何复杂查询都能满足, 见下述示例)
     *   ['mid', '=', 1], 基本形式, 元素1为字段名, 2为比较运算符, 3为比较值, 将生成, "mid = 1"
     *   ['mid', 1], 基本简写形式, 省掉比较运算符则将其默认作为 等于比较运算符, 将生成, "mid = 1"
     *   ['exists', 1], exists特殊格式, 用于无比较条件左值(即字段名)的情况, 将生成, "EXISTS 1"
     *   [['create_time', 'between', [$time1, $time2]], 'and', ['is_deleted', 0]], 多条件与逻辑形式, 将生成, "create_time BETWEEN $time1 AND $time2 AND is_deleted = 0"
     *   [['create_time', 'between', [$time1, $time2]], ['is_deleted', 0]], 多条件缺省逻辑形式, 将当作与逻辑, 将生成, "create_time BETWEEN $time1 AND $time2 AND is_deleted = 0"
     *   [['create_time', 'between', [$time1, $time2]], 'or', ['is_deleted', 0]], 多条件或逻辑形式, 将生成, "create_time BETWEEN $time1 AND $time2 OR is_deleted = 0"
     *   [['mid', 1], [['is_deleted', 1], 'or', ['is_banned', 1]]], 子条件形式, 将生成, "mid = 1 AND (is_deleted = 1 OR is_banned = 1)"
     *   [raw('mid = 1')], 原生sql语句形式, (条件比较复杂且无需防注入时可以用这种形式简化编码)
     *   [new WhereSchema], WhereSchema对象形式, 一般用不到, 但支持这种
     * </pre>
     * @param string|int|float|false|RawBuilder|SelectStatement $operator 条件比较运算符
     * <pre>
     * "="|">"|">="|"<"|"<="|"<>"|"like"|"not like"|"notlike", 这些运算符时, $value可为字符串|数字|null, 如['mobile', 'like', '186%']
     * "between"|"not between", 这些运算符时, $value为一个两元素的数组, 如 ['create_time', 'between', [$time1, $time2]], 将生成, "create_time BETWEEN $time1 AND $time2"
     * "in"|"not in", 这些运算符时, $value为数组, 如 ['mid', 'in', [1,2,3]], 将生成, "mid in (1,2,3)"
     * "exists"|"not exists", 这些运算符时, 可以省略$columnName, 后续参数向前顺移
     * all, 所有运算符的值皆可为 RawBuilder|SelectStatement
     * </pre>
     * @param string|int|float|false|RawBuilder|SelectStatement $value 条件比较值, (根据比较运算确定值类型, 详细说明参见 $operator 参数注解)
     * @return $this
     */
    public function where(string|array|RawBuilder|WhereSchema $columnName, string|int|float|false|RawBuilder|SelectStatement $operator = false, string|int|float|array|false|RawBuilder|SelectStatement $value = false): self {
        $this->queryBuilder->addWhere($columnName, $operator, $value, 'AND');
        return $this;
    }

    /**
     * 添加与逻辑查询条件, (同 $this->where, 是其完整语义版)
     * @param string|array|RawBuilder|WhereSchema $columnName
     * @param string|int|float|false|RawBuilder|SelectStatement $operator
     * @param string|int|float|false|RawBuilder|SelectStatement $value
     * @return $this
     */
    public function andWhere(string|array|RawBuilder|WhereSchema $columnName, string|int|float|false|RawBuilder|SelectStatement $operator = false, string|int|float|array|false|RawBuilder|SelectStatement $value = false): self {
        return $this->where($columnName, $operator, $value);
    }

    /**
     * 添加或逻辑查询条件, 入参详情参见 "添加查询条件"
     * @param string|array|RawBuilder|WhereSchema $columnName
     * @param string|int|float|false|RawBuilder|SelectStatement $operator
     * @param string|int|float|false|RawBuilder|SelectStatement $value
     * @return $this
     */
    public function orWhere(string|array|RawBuilder|WhereSchema $columnName, string|int|float|false|RawBuilder|SelectStatement $operator = false, string|int|float|array|false|RawBuilder|SelectStatement $value = false): self {
        $this->queryBuilder->addWhere($columnName, $operator, $value, 'OR');
        return $this;
    }

    /**
     * 添加分组条件
     * @param string|array|RawBuilder $columns 分组依据字段
     * <pre>
     * string, 逗号(,)分隔的分组依据字段字符串, 如,
     * RawBuilder, 原生SQL语句, 后面会被自动包成array, 如, raw("DATE(time_create)")
     * array, 分组依据字段列表, 如, ['field1', raw("DATE(time_create)")]
     * </pre>
     * @param bool $isAutoRaw 是否自动 RawBuilder 包裹, 默认, true (若)
     * @return $this
     */
    public function group(string|array|RawBuilder $columns, bool $isAutoRaw = false): self {
        $this->queryBuilder->setGroup($columns, $isAutoRaw);
        return $this;
    }

    /**
     * 给group添加having条件, 入参同where, 详情参见 "添加查询条件"
     * @param string|array|RawBuilder|WhereSchema $columnName
     * @param string|int|float|false|RawBuilder|SelectStatement $operator
     * @param string|int|float|false|RawBuilder|SelectStatement $value
     * @return $this
     */
    public function having(string|array|RawBuilder|WhereSchema $columnName, string|int|float|false|RawBuilder|SelectStatement $operator = false, string|int|float|array|false|RawBuilder|SelectStatement $value = false): self {
        $this->queryBuilder->addHaving($columnName, $operator, $value);
        return $this;
    }

    /**
     * 设置排序条件
     * @param string|RawBuilder|array $column 排序列
     * <pre>
     * string, 会自动包裹为[[$column, $order]], 如, "[['mid', 'desc']]"
     * RawBuilder, 会自动包裹为[[$column]],
     * array, 排序规则组, 参考示例
     *   [['level', 'desc']], 按level逆序排列
     *   ['sort_number'], 会自动转成 "[['sort_number']]", 省略了参2排序方式参数, 则将按sort_number顺序排列,
     *   [[raw('count(1)'), 'desc']], 复杂排序字段支持
     *   [['count(1)', 'desc']], 复杂排序字段支持, 当设置 $isAutoRaw 为true时才能这么写, 为了方便用户编码, 默认设置了该参为true, 所以使用需注意不要接收用户数据作为排序字段, 或者将$isAutoRaw设为false并手动raw
     *   [raw('count(1) desc')], raw形式排序条件完整语句形式
     *   [['level', 'desc'], 'sort_number'], 复合排序条件元素形式, 将生成, "ORDER BY level DESC, sort_number", 也支持其他形式排序条件
     * </pre>
     * @param string|bool|null $order 排序方式, 默认, 'ASC'
     * @param bool $isAutoRaw 是否对所传字段参数做RawBuilder包裹, 默认, true
     * <pre>
     *  true, 自动包裹, 方便函数类复杂查询, 如COUNT/SUM等
     *  false, 不包裹, 自动对所传字段做合法性校验
     * </pre>
     * @return $this
     */
    public function order(string|array|RawBuilder $column, string|bool|null $order = null, bool $isAutoRaw = false): self {
        $this->queryBuilder->addOrder($column, $order, $isAutoRaw);
        return $this;
    }

    /**
     * 设置记录截取量
     * @param int $limit 截取条数
     * @param int $offset 起始截取偏移量, 默认, 0
     * @return $this
     */
    public function limit(int $limit, int $offset = 0): self {
        $this->queryBuilder->setLimit($limit, $offset);
        return $this;
    }

    /**
     * 联合查询
     * @param SelectStatement|RawBuilder $statement 将要被联合的语句
     * @param bool $isAll 是否全部联合, 默认true
     * @return $this
     */
    public function union(SelectStatement|RawBuilder $statement, bool $isAll = true): self {
        $this->queryBuilder->addUnion($statement, $isAll);
        return $this;
    }


    /**
     * 构建查询语句实例, 入参同select, 详情参见"多记录查询"
     * @param string|array|RawBuilder|null $columns 待查之列
     * @param bool $isDistinct 是否对结果记录排重
     * @param bool $isAutoRaw 是否对所传字段参数做RawBuilder包裹, 默认, true
     * <pre>
     *  true, 自动包裹, 方便函数类复杂查询, 如COUNT/SUM等
     *  false, 不包裹, 自动对所传字段做合法性校验
     * </pre>
     * @return SelectStatement
     */
    public function buildSelect(string|array|RawBuilder|null $columns = null, bool $isDistinct = false, bool $isAutoRaw = true): SelectStatement {
        return $this->queryBuilder->buildSelect($columns, $isDistinct, $isAutoRaw);
    }


    /**
     * 多记录查询
     * @param string|array|RawBuilder|null $columns 待查之列, 默认, null
     * <pre>
     * 格式样例:
     *   null, "*"
     *   "field1,field2"
     *   ["field1", "f2"=>"field2"], ["field1", "field2 fs"], 推荐第一种形式
     *   raw("count(1)"), 当isAutoRaw设为true的时候, 可以直接用"count(1)"
     * 如果字段名是从请求入参接收的, 请做好防注入工作或者指定isAutoRaw为false防注入
     * </pre>
     * @param string|RawBuilder|null $indexColumn 以此字段作为查询结果数组下标
     * @param bool $isDistinct 是否对结果排重, 默认, false
     * @param bool $isAutoRaw 是否对所传字段参数做RawBuilder包裹, 默认, true
     * <pre>
     *  true, 自动包裹, 方便函数类复杂查询, 如COUNT/SUM等
     *  false, 不包裹, 自动对所传字段做合法性校验
     * </pre>
     * @return array
     */
    public function select(string|array|RawBuilder|null $columns = null, string|RawBuilder|null $indexColumn = null, bool $isDistinct = false, bool $isAutoRaw = true): array {
        $statement = $this->queryBuilder->buildSelect($columns, $isDistinct, $isAutoRaw);
        if ($indexColumn) {
            $statement->getSelectSchema()->extendColumn($indexColumn);
            $indexColumn = $statement->getSelectSchema()->columnToKey($indexColumn);
        }
        return $this->proxy->queryAll($statement, $indexColumn);
    }

    /**
     * 多记录查询, 返回迭代器, 惰性内存写读取结果
     * @param string|array|RawBuilder|null $columns 待查之列, 默认, null
     * <pre>
     * 格式样例:
     *   null, "*"
     *   "field1,field2"
     *   ["field1", "f2"=>"field2"], ["field1", "field2 fs"], 推荐第一种形式
     *   raw("count(1)"), 当isAutoRaw设为true的时候, 可以直接用"count(1)"
     * 如果字段名是从请求入参接收的, 请做好防注入工作或者指定isAutoRaw为false防注入
     * </pre>
     * @param bool $isDistinct 是否对结果排重, 默认, false
     * @param Closure|null $decorator 结果装饰器, 装饰改变返回结果
     * @param bool $isAutoRaw 是否对所传字段参数做RawBuilder包裹, 默认, true
     * <pre>
     *  true, 自动包裹, 方便函数类复杂查询, 如COUNT/SUM等
     *  false, 不包裹, 自动对所传字段做合法性校验
     * </pre>
     * @return Iterator
     */
    public function each(string|array|RawBuilder|null $columns = null, bool $isDistinct = false, Closure|null $decorator = null, bool $isAutoRaw = true): Iterator {
        $statement = $this->queryBuilder->buildSelect($columns, $isDistinct, $isAutoRaw);
        return $this->proxy->queryEach($statement, $decorator);
    }

    /**
     * 单记录查询
     * @param string|array|RawBuilder|null $columns 待查之列, 默认, null
     * <pre>
     * 格式样例:
     *   null, "*"
     *   "field1,field2"
     *   ["field1", "f2"=>"field2"], ["field1", "field2 fs"], 推荐第一种形式
     *   raw("count(1)"), 当isAutoRaw设为true的时候, 可以直接用"count(1)"
     * 如果字段名是从请求入参接收的, 请做好防注入工作或者指定isAutoRaw为false防注入
     * </pre>
     * @param bool $isAutoRaw 是否对所传字段参数做RawBuilder包裹, 默认, true
     * <pre>
     *  true, 自动包裹, 方便函数类复杂查询, 如COUNT/SUM等
     *  false, 不包裹, 自动对所传字段做合法性校验
     * </pre>
     * @return array|false
     */
    public function find(string|array|RawBuilder|null $columns = null, bool $isAutoRaw = true): array|false {
        $statement = $this->queryBuilder->setLimit(1, 0)->buildSelect($columns, false, $isAutoRaw);
        return $this->proxy->queryOne($statement);
    }

    /**
     * 按指定单列的多记录查询
     * @param string|array|RawBuilder|null $column 待查指定列
     * <pre>
     * 格式样例:
     *   "field1"
     *   raw("count(1)"), 当isAutoRaw设为true的时候, 可以直接用"count(1)"
     * 如果字段名是从请求入参接收的, 请做好防注入工作或者指定isAutoRaw为false防注入
     * </pre>
     * @param string|RawBuilder|null $indexColumn 以此字段作为查询结果数组下标
     * @param bool $isDistinct 是否对结果排重, 默认, false
     * @param bool $isAutoRaw 是否对所传字段参数做RawBuilder包裹, 默认, true
     * <pre>
     *  true, 自动包裹, 方便函数类复杂查询, 如COUNT/SUM等
     *  false, 不包裹, 自动对所传字段做合法性校验
     * </pre>
     * @return array
     */
    public function column(string|array|RawBuilder|null $column, string|RawBuilder|null $indexColumn = null, bool $isDistinct = false, bool $isAutoRaw = true): array {
        $statement = $this->queryBuilder->buildSelect($column, $isDistinct, $isAutoRaw);
        $column = $statement->getSelectSchema()->columnToKey($column);
        if ($indexColumn) {
            $statement->getSelectSchema()->extendColumn($indexColumn);
            $indexColumn = $statement->getSelectSchema()->columnToKey($indexColumn);
        }
        return $this->proxy->queryAll($statement, $indexColumn, $column);
    }

    /**
     * 按指定列的单记录查询
     * @param string|array|RawBuilder|null $column 待查指定列
     * <pre>
     * 格式样例:
     *   "field1"
     *   raw("count(1)"), 当isAutoRaw设为true的时候, 可以直接用"count(1)"
     * 如果字段名是从请求入参接收的, 请做好防注入工作或者指定isAutoRaw为false防注入
     * </pre>
     * @param bool $isAutoRaw 是否对所传字段参数做RawBuilder包裹, 默认, true
     * <pre>
     *  true, 自动包裹, 方便函数类复杂查询, 如COUNT/SUM等
     *  false, 不包裹, 自动对所传字段做合法性校验
     * </pre>
     * @return string|int|float|null|false
     */
    public function value(string|array|RawBuilder|null $column, bool $isAutoRaw = true): string|int|float|null|false {
        $statement = $this->queryBuilder->setLimit(1, 0)->buildSelect($column, false, $isAutoRaw);
        return $this->proxy->queryColumn($statement);
    }

    /**
     * 统计计数查询
     * @param string $column 待统计列名
     * @return int|null|false
     */
    public function count(string $column = '1'): int|null|false {
        $column = SchemaAbstract::tableWrapThrow($column);
        $statement = $this->queryBuilder->setLimit(1, 0)->buildSelect("COUNT({$column})", false, true);
        return $this->proxy->queryColumn($statement);
    }

    /**
     * 存在与否查询
     * @return bool
     */
    public function exists(): bool {
        $statement = $this->queryBuilder->setLimit(1, 0)->buildSelect(1, false, false);
        return (bool) $this->proxy->queryColumn($statement);
    }

    /**
     * 最大值查询
     * @param string $column 待查指定列
     * @return string|int|float|null|false
     */
    public function max(string $column): string|int|float|null|false {
        $column = SchemaAbstract::tableWrapThrow($column);
        $statement = $this->queryBuilder->setLimit(1, 0)->buildSelect("MAX({$column})", false, true);
        return $this->proxy->queryColumn($statement);
    }

    /**
     * 最小值查询
     * @param string $column 待查指定列
     * @return string|int|float|null|false
     */
    public function min(string $column): string|int|float|null|false {
        $column = SchemaAbstract::tableWrapThrow($column);
        $statement = $this->queryBuilder->setLimit(1, 0)->buildSelect("MIN({$column})", false, true);
        return $this->proxy->queryColumn($statement);
    }

    /**
     * 平均值查询
     * @param string $column 待查指定列
     * @return string|int|float|null|false
     */
    public function avg(string $column): string|int|float|null|false {
        $column = SchemaAbstract::tableWrapThrow($column);
        $statement = $this->queryBuilder->setLimit(1, 0)->buildSelect("AVG({$column})", false, true);
        return $this->proxy->queryColumn($statement);
    }

    /**
     * 列和值查询
     * @param string $column 待查指定列
     * @return string|int|float|null|false
     */
    public function sum(string $column): string|int|float|null|false {
        $column = SchemaAbstract::tableWrapThrow($column);
        $statement = $this->queryBuilder->setLimit(1, 0)->buildSelect("SUM({$column})", false, true);
        return $this->proxy->queryColumn($statement);
    }

    /**
     * 插入数据
     * @param array $data 待插入数据
     * <pre>
     * 单条或批量插入
     * Map型数据, 单条插入, 如, ['field1' => 1, 'f2' => 2], 此时函数返回所插入数据的id
     * Map为元素的数组, 批量插入, 如, [['field1' => 1, 'f2' => 2]], 此时函数将返回被插入的条数
     * </pre>
     * @param bool|null $ignoreOrReplace 三相类型, 指定重复数据处理规则, 默认, null
     * <pre>
     *  null, 普通插入
     *  true, 忽略重复插入, 若插入数据重复, 则不会插入该数据
     *  false, 替换插入, 若插入主键重复, 则将删除老数据, 插入新数据
     * </pre>
     * @return int|string
     */
    public function insert(array $data, bool|null $ignoreOrReplace = null): int|string {
        $statement = $this->queryBuilder->buildInsert($data, $ignoreOrReplace);
        if ($statement->isBatch()) {
            return $this->proxy->queryGetAffectedCount($statement);
        } else {
            return $this->proxy->queryGetInsertId($statement);
        }
    }

    /**
     * 按查询结果插入数据
     * @param SelectStatement|RawBuilder $selectStatement 查询语句
     * @param string|array|null $columns 写入数据对应字段, 支持逗号分隔的字段名字符串, 或字段名数组
     * @param bool|null $ignoreOrReplace 三相类型, 指定重复数据处理规则, 默认, null
     * <pre>
     *  null, 普通插入
     *  true, 忽略重复插入, 若插入数据重复, 则不会插入该数据
     *  false, 替换插入, 若插入主键重复, 则将删除老数据, 插入新数据
     * </pre>
     * @return int
     */
    public function insertSelect(SelectStatement|RawBuilder $selectStatement, string|array|null $columns = null, bool|null $ignoreOrReplace = null): int {
        $statement = $this->queryBuilder->buildInsertSelect($selectStatement, $columns, $ignoreOrReplace);
        return $this->proxy->queryGetAffectedCount($statement);
    }

    /**
     * 更新数据
     * @param array $data 待更新的字段Map, 如, ['field1' => 1, 'f2' => 2]
     * @param bool|null $allowEmptyConditionOrMustEqual 三相类型, 安全更新控制规则, 默认, null
     * <pre>
     *  null, 不允许不带where条件的更新
     *  true, 允许不带where条件的更新
     *  false, 不允许不带where等值(id = 1 或 id in (1, 2))条件的更新
     * </pre>
     * @return int
     */
    public function update(array $data, bool|null $allowEmptyConditionOrMustEqual = null): int {
        $statement = $this->queryBuilder->buildUpdate($data, $allowEmptyConditionOrMustEqual);
        return $this->proxy->queryGetAffectedCount($statement);
    }

    /**
     * 删除记录
     * @param string|array|null $tableNames 需删除记录的表, 支持逗号分隔的表名字符串, 或表名数组
     * @param bool|null $allowEmptyConditionOrMustEqual 三相类型, 安全删除控制规则, 默认, false
     * <pre>
     *  null, 不允许不带where条件的删除
     *  true, 允许不带where条件的删除
     *  false, 不允许不带where等值(id = 1 或 id in (1, 2))条件的删除
     * </pre>
     * @return int
     */
    public function delete(string|array|null $tableNames = null, bool|null $allowEmptyConditionOrMustEqual = false): int {
        $statement = $this->queryBuilder->buildDelete($tableNames, $allowEmptyConditionOrMustEqual);
        return $this->proxy->queryGetAffectedCount($statement);
    }

    /**
     * 原生SQL查询
     * @param string $statement SQL语句
     * @param array $params 占位参数Map, 如, ['id' => 1]
     * @param mixed $fetch_style 从该参数起, 按序为PDO::fetchAll参数
     * @return array
     */
    public function query(string $statement, array $params = [], mixed $fetch_style = PDO::FETCH_BOTH): array {
        $fetchArgs = array_slice(func_get_args(), 2);
        return $this->proxy->query($statement, $params, $fetchArgs);
    }

    /**
     * 原生SQL执行
     * @param string $statement SQL语句
     * @param array $params 占位参数Map, 如, ['id' => 1]
     * @return int|string
     */
    public function execute(string $statement, array $params = []): int|string {
        return $this->proxy->execute($statement, $params);
    }

    /**
     * 开启事务
     * @return Transaction
     */
    public function begin(): Transaction {
        return $this->proxy->begin($this);
    }

    /**
     * 取最后执行的SQL语句
     * @return string
     */
    public static function lastSql(): string {
        [$statement, $params] = DbProxy::getLastStatement();
        return StatementAbstract::fill($statement, $params);
    }

    /**
     * 取已执行的所有SQL语句, (最后的 \dce\db\proxy\DbProxy::$max_statement_log 条)
     * @return string[]
     */
    public static function executedSqlList(): array {
        $statements = DbProxy::getStatements();
        $sqlList = [];
        foreach ($statements as [$statement, $params]) {
            $sqlList[] = StatementAbstract::fill($statement, $params);
        }
        return $sqlList;
    }

    /**
     * 开启数据库操作的快捷函数
     */
    public static function enableShortcut(): void {
        require_once __DIR__ . "/Shortcut.php";
    }
}
