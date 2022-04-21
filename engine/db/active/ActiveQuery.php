<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020-12-13 09:39
 */

namespace dce\db\active;

/**
 * @template T of ActiveRecord
 */
abstract class ActiveQuery {
    /** @var list<ActiveRelation> */
    readonly protected array $withRelations;

    /**
     * 设置即时加载关联数据
     * @param string ...$relationNames
     * @return $this
     * @throws ActiveException
     */
    public function with(string ... $relationNames): static {
        $this->withRelations = array_map(function($name) {
            $relation = static::getActiveRecordClass()::getActiveRelation(static::getActiveRecordClass()::toModelKey($name));
            ! $relation && throw (new ActiveException(ActiveException::RELATION_NAME_INVALID))->format($name);
            return $relation;
        }, $relationNames);
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
     * @param class-string<T> $activeRecordClass
     */
    abstract public function __construct(string $activeRecordClass);

    /**
     * 取活动记录类名
     * @return class-string<T>
     */
    abstract public function getActiveRecordClass(): string;
}