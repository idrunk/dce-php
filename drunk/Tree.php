<?php
/**
 * Author: Drunk
 * Date: 2020-04-14 11:11
 */

namespace drunk;

abstract class Tree {
    /**
     * 树ID
     * @var string|null
     */
    public string|null $id = null;

    /** @var static|null 父树 */
    public self|null $parent = null;

    /** @var static[] 子树集 */
    public array $children = [];

    /**
     * 添加子节点
     * @param static $child
     * @param string|null $key
     */
    public function addChild (self $child, string|null $key = null): void {
        $child->id = $key;
        $child->parent = $this;
        null === $key
            ? $this->children[] = $child
            : $this->children[$key] = $child;
    }

    /**
     * 根据子节点下标取该节点
     * @param string $key
     * @return self|null
     */
    public function getChild (string $key): self|null {
        return $this->children[$key] ?? null;
    }

    /**
     * 取父族关系树ID集
     * @return string[]
     */
    public function getFamilyIds(): array {
        $ids = [$this->id];
        $parent = $this;
        while (($parent = $parent->parent) && ($id = $parent->id)) {
            $ids[] = $id;
        }
        $ids = array_reverse($ids);
        return $ids;
    }

    /**
     * 判断是否存在某个下标子节点
     * @param string $key
     * @return bool
     */
    public function has (string $key): bool {
        return key_exists($key, $this->children);
    }

    /**
     * 是否空树
     * @return bool
     */
    public function isEmpty (): bool {
        return ! $this->children;
    }

    /**
     * 遍历全树执行回调函数
     * @param callable $callback(Tree $child, Tree $parent)
     */
    public function traversal (callable $callback) {
        $parents = [$this];
        while ($parent = array_pop($parents)) {
            $children = $parent->children;
            foreach ($children as $child) {
                $parents[] = $child;
                call_user_func_array($callback, [$child, $parent]);
            }
        }
    }

    /**
     * 子节点数组化
     * @return array
     */
    protected function childrenArrayify (): array {
        $children = [];
        foreach ($this->children as $child) {
            $children[] = $child->arrayify();
        };
        return $children;
    }
}
