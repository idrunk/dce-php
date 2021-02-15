<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020-12-13 09:39
 */

namespace dce\db\active;

abstract class ActiveQuery {
    protected DbActiveRecord $activeRecord;

    protected bool $arrayify = false;

    protected array $relationNames = [];

    /**
     * 设置即时加载关联数据
     * @param string ...$relationNames
     * @return $this
     */
    public function with(string ... $relationNames): static {
        $this->relationNames = $relationNames;
        return $this;
    }

    /**
     * 设置按数组返回查询结果而非活动记录对象
     * @return $this
     */
    public function arrayify(): static {
        $this->arrayify = true;
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
     * @param array|\ArrayAccess $array1
     * @param array|\ArrayAccess $array2
     * @param array $relation
     * @return bool
     */
    protected static function relationMatch($array1, $array2, array $relation): bool {
        foreach ($relation as $k1=>$k2) {
            // notice 不能用null值比较关联, 无意义, 所以有null则为不匹配
            if (! isset($array1[$k1]) || ! isset($array2[$k2]) || $array1[$k1] != $array2[$k2]) {
                return false;
            }
        }
        return true;
    }

    abstract public function __construct(DbActiveRecord $activeRecord);

    /**
     * 取活动记录实例
     * @return ActiveRecord
     */
    abstract public function getActiveRecord(): ActiveRecord;
}