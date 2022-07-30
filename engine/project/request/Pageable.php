<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2022-06-27 21:11
 */

namespace dce\project\request;

use dce\db\active\ActiveRecord;

class Pageable {
    readonly public int $pageSize;
    public int $page;
    public int $offset;
    public int $total;
    public int $totalPage;
    public array $extAttrs = [];
    public array $rootAttrs = [];
    /** @var array<ActiveRecord> */
    public array $list = [];

    public function __construct(
        public array $filters = [],
        int $page = 0,
        int $pageSize = 0,
    ){
        $pageSize < 1 && $pageSize = $filters['pageSize'] ?? 0;
        $pageSize < 1 && $pageSize = 20;
        $this->pageSize = $pageSize;
        $page < 1 && $page = $filters['page'] ?? 0;
        $page < 1 ? $this->setOffset($filters['offset'] ?? 0) : $this->setPage($page);
    }

    public function setPage(int $page): void {
        $this->page = $page;
        $this->offset = ($page - 1) * $this->pageSize;
    }

    public function setOffset(int $offset): void {
        $this->page = floor($offset / $this->pageSize) + 1;
        $this->offset = $offset;
    }

    public function setTotal(int $total): void {
        $this->total = $total;
        $this->totalPage = ceil($total / $this->pageSize);
    }

    /** @param array<ActiveRecord> $list */
    public function setList(array $list): void {
        $this->list = $list;
    }

    public function setExt(string $key, mixed $value): void {
        $this->extAttrs[$key] = $value;
    }

    public function setAttr(string $key, mixed $value): void {
        $this->rootAttrs[$key] = $value;
    }

    public function getAttr(string $key): mixed {
        return $this->rootAttrs[$key];
    }

    public function hasAttr(string $key): bool {
        return key_exists($key, $this->rootAttrs);
    }

    public function build(array $list = []): array {
        $resp = $this->rootAttrs;
        isset($this->page) && $resp['page'] = $this->page;
        isset($this->total) && $resp['total'] = $this->total;
        $resp['list'] = $list ?: array_map(fn($ar) => $ar->extract(), $this->list);
        return $resp;
    }
}