<?php
/**
 * Author: Drunk
 * Date: 2019/9/24 11:52
 */

namespace dce\db\active;

use dce\model\ModelException;
use drunk\Structure;

class ActiveRelation {
    /** @var list<array{dbPrimary: string, dbForeign: string, modelPrimary: string, modelForeign: string}> */
    readonly private array $relationColumns;

    /** @var list<ActiveRelation> 反向的via关系集，用于数据映射 */
    private array $reversedVias = [];

    /** @var array<string, mixed> 主记录过滤条件，仅对匹配的主记录绑定关系数据 */
    private array $primaryQualifiers = [];

    /**
     * @param string $name
     * @param class-string<ActiveRecord> $primaryActiveRecordClass
     * @param class-string<ActiveRecord> $foreignActiveRecordClass
     * @param array $relationMapping
     * @param bool $hasOneBool
     * @param ActiveRelation|null $via 依赖的关系实例
     */
    public function __construct(
        readonly public string $name,
        readonly private string $primaryActiveRecordClass,
        readonly public string $foreignActiveRecordClass,
        array $relationMapping,
        private bool $hasOneBool,
        readonly public ActiveRelation|null $via
    ) {
        $this->relationColumns = array_map(fn($kv) => [
            'dbForeign' => $foreignActiveRecordClass::toDbKey($kv[0]), 'dbPrimary' => $primaryActiveRecordClass::toDbKey($kv[1]),
            'modelForeign' => $foreignActiveRecordClass::toModelKey($kv[0]), 'modelPrimary' => $primaryActiveRecordClass::toModelKey($kv[1]),
        ], Structure::arrayEntries($relationMapping));
        $this->reversedVias[] = $this;
        if ($via) do {array_unshift($this->reversedVias, $via);} while($via = $via->via);
    }

    public function qualify(array $primaryQualifiers): self {
        $this->primaryQualifiers = array_reduce(array_keys($primaryQualifiers),
            fn($qs, $qk) => $qs + [$this->primaryActiveRecordClass::toModelKey($qk) => $primaryQualifiers[$qk]], []);
        return $this;
    }

    public function getReversedVias(): array {
        return $this->reversedVias;
    }

    /**
     * 取活动记录查询器
     * @return ActiveQuery
     */
    private function newForeignActiveQuery(): ActiveQuery {
        return $this->foreignActiveRecordClass::query();
    }

    /**
     * 是否一对一查询
     * @return bool
     */
    public function isHasOne(): bool {
        return $this->hasOneBool;
    }

    /**
     * 查询数据库（用于查询单个活动记录实例的关系数据）
     * @return ActiveRecord|ActiveRecord[]|false
     * @throws ActiveException|ModelException
     */
    public function screen(ActiveRecord $primaryActiveRecord): ActiveRecord|array|false {
        $activeQuery = $this->loadCarryActiveRecord($primaryActiveRecord);
        return $this->hasOneBool
            ? ($activeQuery?->find() ?: false)
            : ($activeQuery?->select() ?: []);
    }

    /**
     * 取关联关系映射表
     * @return list<array{dbPrimary: string, dbForeign: string, modelPrimary: string, modelForeign: string}>
     * @throws ActiveException
     */
    public function getRelationColumns(): array {
        ! $this->relationColumns && throw new ActiveException(ActiveException::NO_RELATION_MAPPING);
        return $this->relationColumns;
    }

    /**
     * 查询活动记录的关系数据，若无法以依赖数据生成有效的where条件，则返回null
     * @param ActiveRecord $primaryRecord
     * @return ActiveQuery|null
     * @throws ActiveException
     */
    private function loadCarryActiveRecord(ActiveRecord $primaryRecord): ActiveQuery|null {
        /*
         * 1. 反向的via关系集，保证当前关系对象必然在关系集尾部
         * 2. 遍历前面的via关系时，若取到了关系数据（已在别处或自加载），则直接跳过循环，未取到则跳出循环（无法取到关系数据导致下游关系数据也无法查取）
         * 3. 处理最后via关系时，即处理当前关系时，若依赖关系非数组（hasOne），则转为数组，遍历之，生成or连接的等于判断条件集，查询并将结果绑定到活动记录，然后跳出循环
         */
        $where = [];
        foreach ($this->reversedVias as $via) {
            if ($via === $this) {
                // 因为查出的中间关联数据可能有多条, 所以查询下级数据时应该是多条数据条件的or查询, 而不能用in
                // 如: [id=>tag_id, mid=>mid], 中间关联数据为 [[tag_id=>1, mid=>1], [tag_id=>2, mid=>2]], 则此时的条件应为 [[[id,=,1], [mid,=,1]], or, [[id,=,2], [mid,=,2]]]
                foreach ($via->via ? ($via->via->hasOneBool ? array_filter([$primaryRecord->{$via->via->name} ?? null]) : ($primaryRecord->{$via->via->name} ?? [])) : [$primaryRecord] as $viaDatum)
                    array_push($where, array_map(function($column) use($viaDatum) {
                        $viaDatum->getProperty($column['modelPrimary'], fn() => throw (new ActiveException(ActiveException::RELATION_MODEL_HAVE_NO_FOREIGN))->format($column['modelPrimary']));
                        return [$column['dbForeign'], '=', $viaDatum->{$column['modelPrimary']}];
                    }, $via->getRelationColumns()), 'or');
            } else if ($primaryRecord->{$via->name}) {
                continue;
            }
            break;
        }
        // 如果未查到中间表的数据, 则表示无关联数据, 则不能继续查目标表, 因为空条件会将所有数据查询出来, 但其实都是不相关的数据
        return array_pop($where) ? $this->newForeignActiveQuery()->where($where) : null;
    }

    public function loadCarryActiveRecordList(array $primaryConditionData, array & $relationDataMapping): array {
        // 如果需从关联关系筛选数据, 则递归载入关联关系数据, 否则以主体数据作为关联关系数据
        // 需加载的数据可能已在之前作为中间数据加载过, 所以若已加载过则直接取出即可
        $this->via && $primaryConditionData = key_exists($this->via->name, $relationDataMapping)
            ? $relationDataMapping[$this->via->name] : $this->via->loadCarryActiveRecordList($primaryConditionData, $relationDataMapping);

        // 当ID可能与多个表关联时，需根据类型筛选出正确的关联表
        $this->primaryQualifiers && $primaryConditionData = array_filter($primaryConditionData, function($record) {
            foreach ($this->primaryQualifiers as $prop => $value)
                if ($record->{$prop} !== $value) return false;
            return true;
        });

        // 有via数据时才能查carry数据，否则设为空数组即可，即主数据没有关联的carry数据
        $relationData = [];
        if ($primaryConditionData) {
            // 这里在为单一条件时没问题, 在为多条件时会多查出单条件匹配但可能多条件不匹配的数据, 但对于最终正确结果的匹配无影响
            // 根据依赖关系批量查出所有关联数据, 此处虽然新建了ActiveQuery对象, 但因为主ActiveQuery对象缓存了数据，且非carry查询，所以不会重复发送相同查询请求
            $where = array_reduce($this->getRelationColumns(), function ($cm, $c) use ($primaryConditionData) {
                $relationWhereParams = array_map(fn($conditionDatum) => $conditionDatum->{$c['modelPrimary']}, $primaryConditionData);
                ! $relationWhereParams && throw (new ActiveException(ActiveException::PRIMARY_RECORD_NO_FOREIGN_COLUMN))->format($this->name, $c['modelPrimary']);
                $relationWhereParams = array_filter($relationWhereParams, fn($p) => $p !== null);
                return array_merge($cm, $relationWhereParams ? [[$c['dbForeign'], 'in', array_unique($relationWhereParams)]] : []);
            }, []);
            $where && $relationData = $this->newForeignActiveQuery()->where($where)->select();
        }

        $relationDataMapping[$this->name] = $relationData;
        return $relationData;
    }
}
