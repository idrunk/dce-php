<?php
/**
 * Author: Drunk
 * Date: 2019/8/23 10:59
 */

if (! function_exists('db')) {
    /**
     * 取一个Query实例
     * @param string|null $tableName 目标查询表
     * @param string|null $alias 表别名
     * @param string|null $dbAlias 目标查询库 (对于分库表查询将无视此参数)
     * @return \dce\db\Query
     */
    function db(string|null $tableName = null, string|null $alias = null, string|null $dbAlias = null): dce\db\Query {
        $query = new dce\db\Query($dbAlias);
        if (null !== $tableName) {
            $query->table($tableName, $alias);
        }
        return $query;
    }
}

if (! function_exists('raw')) {
    /**
     * 实例化一个原始SQL语句对象
     * @param string $sql
     * @param bool|array $autoParenthesis
     * @param array $params
     * @return \dce\db\query\builder\RawBuilder
     * @throws \dce\db\query\QueryException
     */
    function raw(string $sql, bool|array $autoParenthesis = true, array $params = []): dce\db\query\builder\RawBuilder {
        return new dce\db\query\builder\RawBuilder($sql, $autoParenthesis, $params);
    }
}
