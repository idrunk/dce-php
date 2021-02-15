<?php
/**
 * Author: Drunk
 * Date: 2019-12-27 16:12
 */

namespace dce\sharding\middleware\data_processor;

use Closure;
use Iterator;

class DbReadEachIterator implements Iterator {
    private array $result;

    private Closure|null $decorator;

    private bool $validBool = true;

    public function __construct(array $result, Closure|null $decorator) {
        $this->result = $result;
        $this->decorator = $decorator;
    }

    public function valid() {
        return $this->validBool;
    }

    public function rewind() {
        reset($this->result);
        $this->validBool = true;
    }

    public function next() {
        if (false === next($this->result)) {
            $this->validBool = false;
        }
    }

    public function key() {
        return key($this->result);
    }

    public function current() {
        $data = current($this->result);
        if ($this->decorator) {
            $data = call_user_func($this->decorator, $data);
        }
        return $data;
    }
}
