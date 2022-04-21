<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 15:13
 */

namespace dce\sharding\middleware\data_processor;

use Closure;
use dce\sharding\middleware\DbDirectiveParser;
use dce\sharding\parser\mysql\list\MysqlColumnParser;
use dce\sharding\parser\mysql\list\MysqlOrderByParser;
use dce\sharding\parser\mysql\MysqlFunctionParser;
use dce\sharding\parser\mysql\statement\MysqlOrderByConditionParser;
use dce\sharding\parser\MysqlParser;
use Iterator;

class DbReadProcessor extends DataProcessor {
    public function __construct(
        private DbDirectiveParser $statementParser
    ) {}

    /**
     * 取一条查询记录
     * @return array|false
     */
    public function queryOne(): array|false {
        $result = current($this->queryAll()) ?: false;
        return $result;
    }

    /**
     * 取一条查询记录的第一个字段标量值
     * @return string|float|false|null
     */
    public function queryColumn(): string|float|null|false {
        $result = $this->queryOne();
        $result = $result ? current($result): false;
        return $result;
    }

    /**
     * (此方法目前无法带来性能提升)
     * @param Closure|null $callback
     * @return Iterator
     */
    public function queryEach(Closure|null $callback = null): Iterator {
        $result = $this->queryAll();
        return new DbReadEachIterator($result, $callback);
    }

    /**
     * 取全部合并的查询记录
     * @param string|null $indexColumn
     * @param string|null $columnColumn
     * @return array
     */
    public function queryAll(string|null $indexColumn = null, string|null $columnColumn = null): array {
        $aggregateMap = self::takeAggregateMap($this->statementParser->shardingSelectColumns);
        if ($this->statementParser->groupBy) { // 分组计算

            // 去掉可能包含查询字段的反引号以便后续合并计算时能与查询结果对应
            $groupColumns = array_map(fn($c) => trim(strval($c), '`'), $this->statementParser->groupBy->conditions);
            $sourceData = self::groupMerge($this->sourceData, $groupColumns, $aggregateMap);
            $result = self::concatAndSortLimit($sourceData, $this->statementParser->orderBy, $this->statementParser->limitConditions);
        } else if ($aggregateMap) { // 聚合计算
            $sourceData = array_column($this->sourceData, 0);
            $result = [self::aggregateMerge($sourceData, $aggregateMap)];
        } else { // 非聚合计算
            $result = self::concatAndSortLimit($this->sourceData, $this->statementParser->orderBy, $this->statementParser->limitConditions);
        }
        $result = self::filterSelectColumns($this->statementParser->selectColumn, $this->statementParser->shardingSelectColumns, $result, $indexColumn, $columnColumn);
        return $result;
    }

    /**
     * 提取查询列, 应用别名, 过滤掉非查询列
     * @param MysqlColumnParser $selectColumns
     * @param MysqlParser[] $shardingColumns
     * @param array $result
     * @param string|null $indexColumn
     * @param string|null $columnColumn
     * @return array
     */
    private static function filterSelectColumns(MysqlColumnParser $selectColumns, array $shardingColumns, array $result, string|null $indexColumn = null, string|null $columnColumn = null): array {
        foreach ($shardingColumns as $k => $column)
            $shardingColumns[$k] = $column->getSelectColumnName();
        $columnAliasNameMap = [];
        foreach ($selectColumns as $column) {
            $field = $column->field->getSelectColumnName();
            if ('*' === $field) break; // 如果是查询所有字段, 则不继续处理
            in_array($field, $shardingColumns) && $columnAliasNameMap[$column->alias ?: $field] = $field;
        }
        // 如果指定了提取字段，且为聚合函数，则将其格式化
        $columnColumn && $columnColumn = (string) MysqlFunctionParser::from($columnColumn) ?: $columnColumn;
        $finalResult = [];
        foreach ($result as $k => $v) {
            if ($columnAliasNameMap) {
                $log = [];
                foreach ($columnAliasNameMap as $alias => $field)
                    $log[$alias] = $v[$field];
            } else {
                $log = $v;
            }
            $k = $indexColumn ? $log[$indexColumn] : $k;
            $columnColumn && $log = $log[$columnColumn];
            $finalResult[$k] = $log;
        }
        return $finalResult;
    }

    /**
     * 取聚合函数映射表
     * @param MysqlParser[] $shardingSelectColumns
     * @return MysqlFunctionParser[]
     */
    private static function takeAggregateMap(array $shardingSelectColumns): array {
        // 聚合函数列集 (供下方做聚合计算)
        $aggregateMap = [];
        foreach ($shardingSelectColumns as $shardingSelectColumn) {
            if ($shardingSelectColumn instanceof MysqlFunctionParser && $shardingSelectColumn->isAggregate)
                $aggregateMap[(string) $shardingSelectColumn] = $shardingSelectColumn;
        }
        return $aggregateMap;
    }

    /**
     * 分组查询源数据合并
     * @param array $sourceData
     * @param array $groupColumns
     * @param MysqlFunctionParser[] $aggregateMap
     * @return array
     */
    private static function groupMerge(array $sourceData, array $groupColumns, array $aggregateMap): array {
        $groupKeyDataMap = [];

        $firstBranchKey = key($sourceData);
        // 从源数据提取需分组记录集
        foreach ($sourceData as $db => $rsList) {
            foreach ($rsList as $k => $log) {
                $mapKey = [];
                foreach ($groupColumns as $column)
                    $mapKey[] = $log[$column];
                $mapKey = implode(';;', $mapKey);
                $groupKeyDataMap[$mapKey][] = [$db, $k, $log];
            }
        }

        // 分组聚合计算
        foreach ($groupKeyDataMap as $groupData) {
            if (count($groupData) < 2) continue; // 仅一条的已分组记录无需继续做合并计算处理
            foreach ($groupData as $i => [$db, $k, $log]) {
                unset($sourceData[$db][$k]); // 从原始数据中删除需分组处理的数据
                $groupData[$i] = $log;
            }
            // 将结果追加到第一个数据源, 后续排序时再合并
            $sourceData[$firstBranchKey][] = self::aggregateMerge($groupData, $aggregateMap);
        }

        return $sourceData;
    }

    /**
     * 聚合数据计算
     * @param array $groupData
     * @param MysqlFunctionParser[] $aggregateMap
     * @return array
     */
    private static function aggregateMerge(array $groupData, array $aggregateMap): array {
        $result = [];
        $firstKey = key($groupData);
        foreach ($groupData as $i => $log) {
            foreach ($log as $column => $value) {
                if ($i === $firstKey) {
                    // 以第一行数据作为非聚合列的值 (mark 后续考虑支持mysql8的新特性, 取组中任意行的值)
                    $result[$column] = $value;
                } else {
                    // 聚合计算
                    $aggregate = $aggregateMap[$column] ?? null;
                    if ($aggregate) {
                        switch ($aggregate->name) {
                            case 'COUNT':
                            case 'SUM':
                                $result[$column] += $value;
                                break;
                            case 'MIN':
                            case 'MAX':
                                if ($aggregate->name === 'MIN' && $value < $result[$column] || $aggregate->name === 'MAX' && $value > $result[$column]) {
                                    $result[$column] = $value;
                                }
                                break;
                        }
                    }
                }
            }
        }

        // 计算平均值
        foreach ($aggregateMap as $column => $aggregate) {
            if ($aggregate instanceof MysqlFunctionParser && 'AVG' === $aggregate->name) {
                $noNameFunction = substr($aggregate, 3);
                $sumValue = $result["SUM{$noNameFunction}"] ?? 0;
                $countValue = $result["COUNT{$noNameFunction}"] ?? 0;
                $result[$column] = $countValue > 0 ? sprintf('%.4f', $sumValue / $countValue): null;
            }
        }

        return $result;
    }

    /**
     * 排序并拼接各数据源的数据, 然后按limit条件截取返回
     * @param array $sourceData
     * @param MysqlOrderByParser|null $orderByParser
     * @param array|null $limitConditions
     * @return array
     */
    private static function concatAndSortLimit(array $sourceData, MysqlOrderByParser|null $orderByParser, array|null $limitConditions): array {
        $sortedList = [];
        $compareIndexFrom = -1;
        $isNeedSort = !! $orderByParser;
        while ($sourceData) {
            $db = key($sourceData);
            $listIndex = key($sourceData[$db]);
            if (null === $listIndex) {
                unset($sourceData[$db]);
                continue;
            }
            $sortItem = $sourceData[$db][$listIndex];
            $insertIndex = $isNeedSort ? self::sortFindInsertIndex($orderByParser->conditions, $sortItem, $sortedList, $compareIndexFrom ++): $compareIndexFrom ++;
            array_splice($sortedList, $insertIndex, 0, [$sortItem]);
            $nextItem = next($sourceData[$db]);
            if (! $nextItem || $insertIndex >= $compareIndexFrom) {
                ! next($sourceData) && reset($sourceData);
                if (! $nextItem) unset($sourceData[$db]);
            }
        }
        $limitConditionCount = count($limitConditions);
        if ($limitConditionCount > 0) {
            $limitConditionCount < 2 && array_unshift($limitConditions, 0);
            [$offset, $limit] = $limitConditions;
            $sortedList = array_slice($sortedList, $offset, $limit);
        }
        return $sortedList;
    }

    /**
     * 计算查找待排项在已排项中的排序偏移位置
     * @param MysqlOrderByConditionParser[] $orderByConditions
     * @param array $sortingItem
     * @param array $sortedList
     * @param int $compareIndexFrom
     * @param array $orderGroupConditions
     * @return int
     */
    private static function sortFindInsertIndex(array $orderByConditions, array $sortingItem, array $sortedList, int $compareIndexFrom, array $orderGroupConditions = []): int {
        $orderColumnIndex = count($orderGroupConditions);
        $orderByCondition = $orderByConditions[$orderColumnIndex];
        $orderByConditionField = $orderByCondition->field->getSelectColumnName();

        $insertIndex = 0;
        if ($sortedList) {
            for ($sortedIndex = $compareIndexFrom; $sortedIndex >= 0; $sortedIndex --) {
                $sortingValue = $orderGroupConditions + [$orderByConditionField => $sortingItem[$orderByConditionField]];
                $compareResult = self::sortConditionCompare($sortedList[$sortedIndex], $sortingValue);

                if (null === $compareResult) {
                    // 比较结果为null则表示当前已排项与待排项未在同组下, 无法进行比较
                    continue;
                } else if ($orderByCondition->isAsc && $compareResult >= 0 || ! $orderByCondition->isAsc && $compareResult <= 0) {
                    if (0 === $compareResult && $orderByCondition = $orderByConditions[++ $orderColumnIndex] ?? null) {
                        // 比较结果为0, 则表示待排项与比对已排项相等, 若还有下个排序条件, 则递归根据下个条件取插入索引
                        $insertIndex = self::sortFindInsertIndex($orderByConditions, $sortingItem, $sortedList, $sortedIndex, $sortingValue);
                    } else {
                        // 若比较结果非0或无下个条件, 则插入索引为当前已排项之后
                        $insertIndex = $sortedIndex + 1;
                    }
                    break;
                } else {
                    // 若当前为正序且比较结果小于0, 或当前为逆序且比较结果大于0, 则表示待排项应在已排项之前(即取代当前已排项的位置)
                    $insertIndex = $sortedIndex;
                }
            }
        }
        return $insertIndex;
    }

    /**
     * 比较排序值与已排参照值大小
     * @param array $sortedReference
     * @param array $sortingValue
     * @return int|null {null: 已排参照值与所排值不再同一个排序分组, -1: 所排值小于参照值, 0: 所排值等于参照值, 1: 所排值大于参照值}
     */
    private static function sortConditionCompare(array $sortedReference, array $sortingValue): int|null {
        $result = null;
        $lastConditionIndex = count($sortingValue) - 1;
        $index = 0;
        foreach ($sortingValue as $k=>$v) {
            $result = $v <=> $sortedReference[$k];
            if ($result) {
                $index < $lastConditionIndex && $result = null;
                break;
            }
            $index ++;
        }
        return $result;
    }
}
