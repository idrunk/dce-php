<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020-12-13 09:39
 */

namespace dce\db\active;

use drunk\Structure;

/**
 * @template T of ActiveRecord
 */
abstract class ActiveQuery {
    /** @var array<array{relation: ActiveRelation, children?: array<>}> */
    readonly protected array $carryRelations;

    readonly protected bool $carryExtends;

    /**
     * 设置即时加载关联数据
     * @param string|array ...$relationName <pre>若需递归，仅支持传递第一个参数，格式如:
     * [grass, appleTree => flower,
     *  tree => [appleTree => flower, orangeTree => [flower, fruit]]
     * ]</pre>
     * @param string ...$relationNames
     * @return $this
     * @throws ActiveException
     */
    public function carry(string|array $relationName, string ... $relationNames): static {
        $relationNames = is_array($relationName) ? $relationName : [$relationName, ... $relationNames];
        $this->carryRelations = self::buildCarryRelation($relationNames, $this->getActiveRecordClass());
        return $this;
    }

    /**
     * 构建树形carry关系
     * @param array $relationNames
     * @param class-string<ActiveRecord> $activeRecordClass
     * @return array
     * @throws ActiveException
     */
    private static function buildCarryRelation(array $relationNames, string $activeRecordClass): array {
        $relations = [];
        foreach ($relationNames as $name => $children) {
            if (is_int($name)) {
                is_array($children) && throw (new ActiveException(ActiveException::RELATION_KEY_INVALID))->format($name);
                $name = $children;
                $children = [];
            } else if ($children) {
                ! is_array($children) && $children = [$children];
            }
            $pack['relation'] = $activeRecordClass::getActiveRelation($activeRecordClass::toModelKey($name));
            ! $pack['relation'] && throw (new ActiveException(ActiveException::RELATION_NAME_INVALID))->format($name);
            $pack['children'] = $children ? self::buildCarryRelation($children, $pack['relation']->foreignActiveRecordClass) : [];
            array_push($relations, $pack);
        }
        return $relations;
    }

    public function carryExtends(bool $carry = true): static {
        $this->carryExtends = $carry;
        return $this;
    }

    /**
     * 数据关系匹配
     * @example ```
     *  var_dump(
     *      relationMatch(['a'=>1, 'b'=>1], ['a'=>1, 'b'=>1, 'c'=>2], ['a'=>'a', 'b'=>'b']), // true
     *      relationMatch(['a'=>1, 'b'=>1], ['a2'=>1, 'c'=>2], ['a'=>'a2']), // true
     *      relationMatch(['a'=>1, 'c'=>2], ['a2'=>1, 'b'=>1], ['a'=>'a2', 'b'=>'b'], ), // false
     *      relationMatch(new ArrayClass(['a'=>1, 'b'=>1]), new ArrayClass(['a'=>'1', 'b'=>1, 'c'=>2]), ['a'=>'a', 'b'=>'b']), // true
     *      relationMatch(new ArrayClass(['a'=>1, 'b'=>1]), new ArrayClass(['a'=>'1', 'c'=>2]), ['a'=>'a']), // true
     *      relationMatch(new ArrayClass(['a'=>null, 'c'=>2]), new ArrayClass(['a'=>null, 'b'=>1]), ['a'=>'a'], ), // false
     *  );
     * ```
     * @param ActiveRecord $foreignRecord
     * @param ActiveRecord $primaryRecord
     * @param array $relationColumns
     * @return bool
     */
    protected static function relationRecordMatch(ActiveRecord $foreignRecord, ActiveRecord $primaryRecord, array $relationColumns): bool {
        foreach ($relationColumns as ['modelPrimary' => $modelPrimary, 'modelForeign' => $modelForeign])
            if (($foreignRecord->$modelForeign ?? null) !== ($primaryRecord->$modelPrimary ?? null)) return false;
        return true;
    }

    /**
     * 将模型属性键值表转查询条件
     * @param array $mapping
     * @return array
     */
    public static function mappingToWhere(array $mapping): array {
        return array_map(fn($kv) => [$kv[0], '=', $kv[1]], Structure::arrayEntries($mapping));
    }

    /**
     * @param class-string<T> $activeRecordClass
     */
    abstract public function __construct(string $activeRecordClass);

    /**
     * 取活动记录类名
     * @return class-string<T>
     */
    abstract public function getActiveRecordClass(): string;
}