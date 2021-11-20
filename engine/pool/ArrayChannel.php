<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/11/20 11:56
 */

namespace dce\pool;

class ArrayChannel extends ChannelAbstract {
    private array $channel = [];

    /** @inheritDoc */
    public function push(mixed $object): bool {
        return array_push($this->channel, $object);
    }

    /** @inheritDoc */
    public function pop(): mixed {
        return array_pop($this->channel);
    }

    public function isEmpty(): bool {
        return empty($this->channel);
    }

    public function length(): int {
        return count($this->channel);
    }
}