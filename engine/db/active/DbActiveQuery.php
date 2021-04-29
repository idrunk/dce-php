<?php
/**
 * Author: Drunk
 * Date: 2019/8/29 18:35
 */

namespace dce\db\active;

use dce\db\proxy\Transaction;
use dce\db\Query;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\schema\WhereSchema;
use dce\db\query\builder\Statement\SelectStatement;
use Iterator;
use JetBrains\PhpStorm\ArrayShape;

class DbActiveQuery extends ActiveQuery {
    private Query $query;

    #[ArrayShape([[
        'relation_queue' => 'array',
        'relation_data' => 'array'
    ]])]
    private array $relationData = [];

    public function __construct(
        protected DbActiveRecord $activeRecord,
    ) {
        $this->query = new Query($this->activeRecord::getProxy());
        $this->query->table($this->activeRecord::getTableName());
    }

    /** @inheritDoc */
    public function getActiveRecord(): DbActiveRecord {
        return $this->activeRecord;
    }

    /**
     * 设置Where条件
     * @param string|array|RawBuilder|WhereSchema $columnName
     * @param string|int|float|false|RawBuilder|SelectStatement $operator
     * @param string|int|float|array|false|RawBuilder|SelectStatement $value
     * @return $this
     */
    public function where(string|array|RawBuilder|WhereSchema $columnName, string|int|float|false|RawBuilder|SelectStatement $operator = false, string|int|float|array|false|RawBuilder|SelectStatement $value = false): self {
        $this->query->where($columnName, $operator, $value);
        return $this;
    }

    /**
     * 设置排序规则
     * @param string|array|RawBuilder $columnName
     * @param string|null $order
     * @return $this
     */
    public function order(string|array|RawBuilder $columnName, string|null $order = null): self {
        $this->query->order($columnName, $order);
        return $this;
    }

    /**
     * 设置记录截取量
     * @param int $limit
     * @param int $offset
     * @return $this
     */
    public function limit(int $limit, int $offset = 0): self {
        $this->query->limit($limit, $offset);
        return $this;
    }

    /**
     * 多记录查询实例化结果集并返回
     * @param string|RawBuilder|null $indexColumn
     * @return ActiveRecord[]
     * @throws ActiveException
     */
    public function select(string|RawBuilder|null $indexColumn = null): array {
        $data = $this->query->select('*', $indexColumn);
        $data = $this->loadAllRelationData($data);
        if ($this->arrayify) {
            // 如果要取数组式的结果, 则不加载关系数据, 因为必须一次性取出, 造成不必要的io浪费
            return $data;
        }
        foreach ($data as $k => $datum) {
            /** @var ActiveRecord $activeRecord */
            $activeRecord = new ($this->activeRecord::class);
            $activeRecord->setQueriedProperties($datum);
            foreach ($datum as $property => $value) {
                // 从普通properties中剔除出属于getterValues的属性赋值到getterValues
                if ($value && in_array($property, $this->relationNames)) {
                    $activeRecord->setGetterValue($property, $value);
                    unset($datum[$property]);
                }
            }
            $data[$k] = $activeRecord;
        }
        return $data;
    }

    /**
     * 将批量查出的 with 的关联数据按映射关系分配绑定到主体数据
     * @param array $data
     * @return array
     * @throws ActiveException
     */
    private function loadAllRelationData(array $data): array {
        if (! $this->relationNames) {
            return $data;
        }
        // 遍历关系名, 批量查询出所有关联关系数据
        foreach ($this->relationNames as $relationName) {
            $this->loadRelationData($relationName, $data);
            ['relation_queue' => $viaRelationQueue] = $this->relationData[$relationName];
            // 遍历主体数据, 将关系数据挂靠于子元素中
            foreach ($data as $key => $datum) {
                // 关系数据可能已经在下面的中间表赋值, 所以若当前关系已赋值, 则无需再重复处理
                if (key_exists($relationName, $datum)) {
                    break;
                }
                // 由于对应关系可能是一对多对多, 所以此处将其转为矩阵, 可以通用化处理多对多的关系数据
                $relationData = [ $datum ];
                /**
                 * 遍历中间关系队列, 主用于获取有中间关联表的关系数据
                 * @var string $viaRelationName
                 * @var ActiveRelation $viaActiveQueryRelation
                 */
                foreach ($viaRelationQueue as [$viaRelationName, $viaActiveQueryRelation]) {
                    $viaRelationData = $this->relationData[$viaRelationName]['relation_data'];
                    $relationMapping = $viaActiveQueryRelation->getMapping();
                    $matchedRelationData = [];
                    foreach ($viaRelationData as $viaRelationDatum) {
                        foreach ($relationData as $relationDatum) {
                            // 如果关联数据与主体数据匹配, 则记录关联数据, 用于后续子数据匹配的主体数据
                            if (self::relationMatch($viaRelationDatum, $relationDatum, $relationMapping)) {
                                $matchedRelationData[] = $viaRelationDatum;
                            }
                        }
                    }
                    if (in_array($viaRelationName, $this->relationNames)) {
                        $datum[$viaRelationName] = $viaActiveQueryRelation->isHasOne() ? ($matchedRelationData[0] ?? null): $matchedRelationData;
                    }
                    // 更新依赖关系映射表以便递归查询下一级数据
                    $relationData = $matchedRelationData;
                }
                $data[$key] = $datum;
            }
        }
        return $data;
    }

    /**
     * 批量查出载入所有 with 的关系数据
     * @param string $relationName
     * @param array $data
     * @param array $viaRelationQueue
     * @return ActiveRecord[]
     * @throws ActiveException
     */
    private function loadRelationData(string $relationName, array $data, array &$viaRelationQueue = []): array {
        // 需加载的数据可能已在之前作为中间数据加载过, 所以若已加载过则直接取出返回即可
        if (key_exists($relationName, $this->relationData)) {
            return $this->relationData[$relationName];
        }
        $activeQueryRelation = $this->activeRecord->callGetter($relationName);
        if (! $activeQueryRelation instanceof ActiveRelation) {
            throw (new ActiveException(ActiveException::RELATION_NAME_INVALID))->format($relationName);
        }
        // 将靠近主体的关系压入表头, 方便推出多级关联数据
        array_unshift($viaRelationQueue, [$relationName, $activeQueryRelation]);
        $viaRelationName = $activeQueryRelation->getVia();
        // 如果需从关联关系筛选数据, 则递归载入关联关系数据, 否则以主体数据作为关联关系数据
        if ($viaRelationName) {
            $conditionRelationData = $this->loadRelationData($viaRelationName, $data, $viaRelationQueue);
        } else {
            $conditionRelationData = $data;
        }
        // 这里在为单一条件时没问题, 在为多条件时会多查出单条件匹配但可能多条件不匹配的数据, 但对于最终正确结果的匹配无影响
        $relationMapping = $activeQueryRelation->getMapping();
        foreach ($relationMapping as $foreignKey => $relationKey) {
            $relationWhereParams = array_column($conditionRelationData, $activeQueryRelation->getActiveQuery()->activeRecord::toModelKey($relationKey));
            if (! $relationWhereParams) {
                throw (new ActiveException(ActiveException::NO_FOREIGN_IN_VIA_GETTER))->format($relationName, $foreignKey);
            }
            // 取一条关联关系数据时允许用户设置limit:1以提升性能, 但如果通过with批量查的, 则不能limit了, 需要清除limit条件
            $activeQueryRelation->getActiveQuery()->where($foreignKey, 'in', $relationWhereParams)->limit(0);
        }
        // 根据依赖关系批量查出所有关联数据
        $relationData = $activeQueryRelation->getActiveQuery()->select();
        $this->relationData[$relationName] = ['relation_queue' => $viaRelationQueue, 'relation_data' => $relationData];
        return $relationData;
    }

    /**
     * 多记录查询, 返回迭代器, 遍历时实例化为活动记录对象
     * @return Iterator
     * @throws ActiveException
     */
    public function each(): Iterator {
        if ($this->relationNames) {
            throw new ActiveException(ActiveException::EACH_NO_SUPPORT_WITH);
        }
        $iterator = $this->query->each('*', false, function ($data) {
            if ($this->arrayify) {
                return $data;
            }
            /** @var DbActiveRecord $activeRecord */
            $activeRecord = new ($this->activeRecord::class);
            $activeRecord->setQueriedProperties($data);
            return $activeRecord;
        });
        return $iterator;
    }

    /**
     * 筛选一条数据库数据, 转为活动记录对象并返回
     * @return ActiveRecord|array|false
     */
    public function find(): ActiveRecord|array|false {
        $data = $this->query->find();
        if ($this->arrayify || empty($data)) {
            return $data;
        }
        $this->activeRecord->setQueriedProperties($data);
        return $this->activeRecord;
    }

    /**
     * 向数据库插入数据
     * @param array $data
     * @param bool|null $ignoreOrReplace
     * @return int|string
     */
    public function insert(array $data, bool|null $ignoreOrReplace = null): int|string {
        return $this->query->insert($data, $ignoreOrReplace);
    }

    /**
     * 更新筛选的数据
     * @param array $data
     * @return int
     */
    public function update(array $data): int {
        return $this->query->update($data, false);
    }

    /**
     * 删除筛选中的数据
     * @return int
     */
    public function delete(): int {
        return $this->query->delete(null, false);
    }

    /**
     * 开启事务
     * @return Transaction
     */
    public function begin(): Transaction {
        return $this->query->begin();
    }
}
