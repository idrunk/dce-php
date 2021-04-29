<?php
/**
 * Author: Drunk
 * Date: 2019/9/24 11:52
 */

namespace dce\db\active;

use drunk\Char;

/**
 * 关联数据查询器
 * Class ActiveRelation
 * @package dce\db\active
 */
class ActiveRelation {
    private bool $hasOneBool = false;

    /** @var bool 标记是否无中间关系数据(无中间关系则无法加载下级关系) */
    private bool $noViaConditionBool = false;

    /** @var ActiveRecord 引用自活动记录 */
    private ActiveRecord $refActiveRecord;

    private array $relationMapping;

    private string|null $viaRelationName;

    public function __construct(
        private ActiveQuery $activeQuery
    ){}

    /**
     * 取活动记录查询器
     * @return ActiveQuery
     */
    public function getActiveQuery(): ActiveQuery {
        return $this->activeQuery;
    }

    /**
     * 设为一对一关联关系
     * @param bool $isTrue
     * @return $this
     */
    public function hasOne(bool $isTrue = true): self {
        $this->hasOneBool = $isTrue;
        return $this;
    }

    /**
     * 是否一对一查询
     * @return bool
     */
    public function isHasOne(): bool {
        return $this->hasOneBool;
    }

    /**
     * 查询数据库
     * @return ActiveRecord|array|false
     * @throws ActiveException
     */
    public function screen(): ActiveRecord|array|false {
        $this->loadRelation();
        if ($this->hasOneBool) {
            return $this->noViaConditionBool ? false : $this->activeQuery->find();
        } else {
            return $this->noViaConditionBool ? [] : $this->activeQuery->select();
        }
    }

    /**
     * 设置关联关系映射表
     * @param ActiveRecord $activeRecord
     * @param array $relationMap
     * @param string|null $viaRelationName
     * @return $this
     */
    public function setMapping(ActiveRecord $activeRecord, array $relationMap, string|null $viaRelationName): self {
        $this->refActiveRecord = $activeRecord;
        $this->relationMapping = $relationMap;
        $this->viaRelationName = $viaRelationName ? Char::camelize($viaRelationName, true) : $viaRelationName;
        return $this;
    }

    /**
     * 取关联关系映射表
     * @return array
     * @throws ActiveException
     */
    public function getMapping(): array {
        if (null === $this->relationMapping) {
            throw new ActiveException(ActiveException::NO_RELATION_MAPPING);
        }
        return $this->relationMapping;
    }

    /**
     * 取中间关系名
     * @return string|null
     */
    public function getVia(): string|null {
        return $this->viaRelationName;
    }

    private function loadRelation(): self {
        $where = [];
        $relationMapping = $this->getMapping();
        // 如果指定了via关联名, 则先查询中间关联关系数据, 再将其映射填充到当前关联关系, 拼接查询条件实体进行数据查询
        if ($this->viaRelationName) {
            /** @var ActiveRelation $viaActiveQueryRelation */
            $viaActiveQueryRelation = $this->refActiveRecord->callGetter($this->viaRelationName);
            $viaRelationMapping = $viaActiveQueryRelation->getMapping();
            foreach ($viaRelationMapping as $foreignKey => $relationKey) {
                // 因为此处是根据主体数据查出中间表数据, 而主体数据就一条, 所以此处为=号筛选
                $viaActiveQueryRelation->getActiveQuery()->where($foreignKey, '=', $this->refActiveRecord->$relationKey);
            }
            // 根据依赖关系批量查出所有中间关联数据
            $viaRelationData = $viaActiveQueryRelation->getActiveQuery()->select();
            if ($viaRelationData) {
                foreach ($viaRelationData as $viaRelationDatum) {
                    $wherePart = [];
                    foreach ($relationMapping as $slavePrimaryKey => $foreignKey) {
                        // 如果所需依赖条件值不存在, 则不能组成查询条件
                        if (! isset($viaRelationDatum[$foreignKey])) {
                            $where = [];
                            break 2;
                        }
                        $wherePart[] = [$slavePrimaryKey, '=', $viaRelationDatum[$foreignKey]];
                    }
                    // 因为查出的中间关联数据可能有多条, 所以查询下级数据时应该是多条数据条件的or查询, 而不能用in
                    // 如: [id=>tag_id, mid=>mid], 中间关联数据为 [[tag_id=>1, mid=>1], [tag_id=>2, mid=>2]], 则此时的条件应为 [[[id,=,1], [mid,=,1]], or, [[id,=,2], [mid,=,2]]]
                    array_push($where, $wherePart, 'OR');
                }
            }
            // 如果未查到中间表的数据, 则表示无关联数据, 则不能继续查目标表, 因为空条件会将所有数据查询出来, 但其实都是不相关的数据
            if ($where) {
                array_pop($where);
            } else {
                $this->noViaConditionBool = true;
            }
        } else {
            foreach ($relationMapping as $slavePrimaryKey => $foreignKey) {
                if (! $this->refActiveRecord->getProperty($foreignKey)) {
                    throw (new ActiveException(ActiveException::RELATION_MODEL_HAVE_NO_FOREIGN))->format($foreignKey);
                }
                $where[] = [$slavePrimaryKey, '=', $this->refActiveRecord->$foreignKey];
            }
        }
        $this->getActiveQuery()->where($where);
        return $this;
    }
}
