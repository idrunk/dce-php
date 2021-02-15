<?php
/**
 * Author: Drunk
 * Date: 2020-02-19 18:44
 */

namespace dce\pool;

use Swoole\Coroutine\Channel;

class CoroutineChannel extends ChannelAbstract {
    private Channel $channel;

    public function __construct(int $capacity = 64) {
        parent::__construct($capacity);
        $this->channel = new Channel($capacity);
    }

    public function push($object): bool {
        return $this->channel->push($object);
    }

    public function pop(): mixed {
        return $this->channel->pop();
    }

    public function isEmpty(): bool {
        return $this->channel->isEmpty();
    }

    public function getLength(): int {
        return $this->channel->length();
    }
}
