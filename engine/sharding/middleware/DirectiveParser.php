<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/12/31 21:06
 */

namespace dce\sharding\middleware;

abstract class DirectiveParser {
    /**
     * 当前指令是否分库查询
     * @return bool
     */
    abstract public function isSharding(): bool;

    /**
     * 当前指令是否插入指令
     * @return bool
     */
    abstract public function isInsert(): bool;

    /**
     * 取查询目标表的分库配置
     * @param string|null $tableName
     * @return ShardingConfig|null
     */
    abstract public function getSharding(string|null $tableName = null): ShardingConfig|null;
}