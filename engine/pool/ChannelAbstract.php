<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/10/19 4:45
 */

namespace dce\pool;

use dce\base\SwooleUtility;
use dce\loader\attr\Sington;

abstract class ChannelAbstract{
    protected int $capacity;

    public function __construct(int $capacity = 0) {
        if ($capacity > 0) {
            $this->setCapacity($capacity);
        }
    }

    /**
     * 可能某些池有容量上限, 可通过计数与此值比对是否超上限, 若无上限, 则无需调用此参数 (实例配置实例带有实例上限数, 该处已做过一次超限拦截)
     * @param int $capacity
     */
    public function setCapacity(int $capacity): void {
        $this->capacity = $capacity;
    }

    /**
     * @param mixed $object
     * @return bool
     */
    abstract public function push(mixed $object): bool;

    /**
     * @return mixed
     */
    abstract public function pop(): mixed;

    abstract public function isEmpty(): bool;

    abstract public function length(): int;

    public static function autoNew(int $capacity = 0): static {
        return Sington::new(SwooleUtility::inSwoole() ? CoroutineChannel::class : ArrayChannel::class, $capacity);
    }
}
