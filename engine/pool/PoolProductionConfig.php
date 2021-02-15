<?php
/**
 * Author: Drunk
 * Date: 2019-3-10 7:39
 */

namespace dce\pool;

use ArrayAccess;
use dce\config\Config;

abstract class PoolProductionConfig extends Config {
    /**
     * 容量
     * @var int
     */
    private int $capacity;

    /**
     * 实例产量
     * @var int
     */
    private int $yields = 0;

    /**
     * 实例生产率
     * @var float
     */
    private float $yieldRate = 0;

    /**
     * 实例销量
     * @var int
     */
    private int $sales = 0;

    /**
     * 实例销率
     * @var float
     */
    private float $salesRate = 0;

    /**
     * 实例池配置类
     * pool_config_slb constructor.
     * @param array|ArrayAccess $config
     * @param int $capacity
     */
    public function __construct(array|ArrayAccess $config, int $capacity) {
        parent::__construct($config);
        $this->capacity = $capacity;
    }

    /**
     * 判断配置是否与当前实例匹配 (是否有对应的属性, 且属性值相等, 可在子类中覆盖该方法按自定逻辑判断)
     * @param array|ArrayAccess $config
     * @return bool
     */
    public function match(array|ArrayAccess $config): bool {
        return $this->matchWithProperties($config, array_keys($this->arrayify()));
    }

    /**
     * 判断传入配置与当前实例是否有指定的属性且属性值相等
     * @param array|ArrayAccess $config
     * @param array $columns
     * @return bool
     */
    protected function matchWithProperties(array|ArrayAccess $config, array $columns): bool {
        foreach ($columns as $column) {
            if (! isset($this[$column]) || $this[$column] != ($config[$column] ?? null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 取生产率
     * @return float
     */
    final public function getYieldRate(): float {
        return $this->yieldRate;
    }

    /**
     * 生产一次
     */
    final public function yield(): void {
        $this->yields ++;
        $this->yieldRate = $this->yields / $this->capacity;
    }

    /**
     * 销毁一次
     */
    final public function destroy(): void {
        $this->yields --;
        $this->yieldRate = $this->yields / $this->capacity;
    }

    /**
     * 取销售率
     * @return float
     */
    final public function getSalesRate(): float {
        return $this->salesRate;
    }

    /**
     * 消费一次
     */
    final public function consume(): void {
        $this->sales ++;
        $this->salesRate = $this->sales / $this->capacity;
    }

    /**
     * 退还一次
     */
    final public function return(): void {
        $this->sales --;
        $this->salesRate = $this->sales / $this->capacity;
    }

    /**
     * 取生产销售率
     */
    final public function getYieldSalesRate(): float {
        return $this->yields > 0 ? $this->sales / $this->yields : 1;
    }
}
