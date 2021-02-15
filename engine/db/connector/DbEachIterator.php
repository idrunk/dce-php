<?php
/**
 * Author: Drunk
 * Date: 2019/10/23 10:51
 */

namespace dce\db\connector;

use Closure;
use Iterator;
use PDO;
use PDOStatement;

class DbEachIterator implements Iterator {
    private PDOStatement $statement;

    private int $offset = 0;

    private int $count;

    private Closure|null $decorator;

    public function __construct(PDOStatement $statement, Closure|null $decorator) {
        $this->statement = $statement;
        $this->count = $statement->rowCount();
        $this->decorator = $decorator;
    }

    public function valid() {
        return $this->offset < $this->count;
    }

    public function rewind() {
        $this->offset = 0;
    }

    public function next() {
        $this->offset ++;
    }

    public function key() {
        return $this->offset;
    }

    public function current() {
        $data = $this->statement->fetch(PDO::FETCH_ASSOC);
        if ($this->decorator) {
            $data = call_user_func($this->decorator, $data);
        }
        return $data;
    }
}
