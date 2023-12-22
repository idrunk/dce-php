<?php
/**
 * Author: Drunk
 * Date: 2020-04-14 11:11
 */

namespace drunk;

use ArrayAccess;
use dce\base\TreeTraverResult;

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
     * @param string|array|null $keys
     */
    public function setChild(self $child, string|array|null $keys = null): void {
        null !== $keys && ! is_array($keys) && $keys = [$keys];
        $key = $keys ? array_pop($keys) : null;
        $child->id ??= $key;
        $parent = $keys ? $this->getChild($keys) : $this;
        $child->parent = $parent;
        null === $key ? $parent->children[] = $child : $parent->children[$key] = $child;
    }

    /**
     * 根据子节点下标取该节点
     * @param string|array $keys
     * @return static|null
     */
    public function getChild(string|array $keys): self|null {
        $keys && ! is_array($keys) && $keys = [$keys];
        $child = $this;
        foreach ($keys as $key)
            if (! $child = $child->children[$key] ?? null) break;
        return $child;
    }

    /**
     * 取父族树集
     * @param Tree|null $until 直到取到某个父级
     * @param bool $elderFirst
     * @return static[]
     */
    public function getParents(self|null $until = null, bool $elderFirst = true): array {
        $elements = [$this];
        $parent = $this;
        while ((! $until || $until !== $parent) && $parent = $parent->parent)
            $elements[] = $parent;
        return $elderFirst ? array_reverse($elements) : $elements;
    }

    /**
     * 取父族树ID集
     * @param Tree|null $until
     * @param bool $elderFirst
     * @return string[]
     */
    public function getParentIds(self|null $until = null, bool $elderFirst = true): array {
        return array_reduce($this->getParents($until, $elderFirst), fn($ids, $element) => [... $ids, ... (null === $element->id ? [] : [$element->id])], []);
    }

    /**
     * 判断是否存在某个下标子节点
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        return key_exists($key, $this->children);
    }

    /**
     * 是否空树
     * @return bool
     */
    public function isEmpty(): bool {
        return ! $this->children;
    }

    /**
     * 遍历全树执行回调函数
     * @param callable $callback(static $child) @return {TreeTraverResult::StopAll: 停止遍历, TreeTraverResult::StopSibling: 跳出兄弟级遍历, TreeTraverResult::StopChild: 不遍历子级}
     */
    public function traversal(callable $callback): void {
        $parents = [$this];
        while ($parent = array_pop($parents)) {
            $count = count($parents);
            foreach ($parent->children as $child) {
                $result = call_user_func_array($callback, [$child]);
                if (TreeTraverResult::StopAll === $result) {
                    break 2;
                } else if (TreeTraverResult::StopSibling === $result) {
                    break;
                } else if (TreeTraverResult::StopChild !== $result) {
                    array_splice($parents, $count, 0, [$child]);
                }
            }
        }
    }

    /**
     * 子节点数组化
     * @return array
     */
    protected function childrenArrayify(): array {
        $children = [];
        foreach ($this->children as $k => $child)
            $children[$k] = $child->arrayify();
        return $children;
    }

    /**
     * 从数组组装对象树
     * @param array $arrays
     * @param static|string|int $pid
     * @param int $deep
     * @param string $primaryKey
     * @param string $parentKey
     * @param bool $pkAsIndex
     * @param int $currentDeep
     * @return static
     */
    public static function from(array $arrays, self|string|int $pid, int $deep = 0, string $primaryKey = 'id', string $parentKey = 'pid', bool $pkAsIndex = false, int $currentDeep = 1): static {
        $parent = $pid;
        if (! $pid instanceof static) {
            $index = Structure::arraySearch(fn($item) => $item[$primaryKey] == $pid, $arrays);
            $parent = new static($index === false ? [$primaryKey => $pid] : $arrays[$index]);
            $parent->id = $pid;
        }
        foreach ($arrays as $k => $v) {
            if ($v[$parentKey] != $parent->id) continue;
            unset($arrays[$k]);
            $child = new static($v);
            $child->id = $v[$primaryKey];
            if (! $deep || $currentDeep < $deep)
                $child->children = self::from($arrays, $child, $deep, $primaryKey, $parentKey, $pkAsIndex, $currentDeep + 1)->children;
            $parent->setChild($child, $pkAsIndex ? $child->id : null);
        }
        return $parent;
    }

    abstract public function __construct(array|ArrayAccess $properties);

    /**
     * 树形数组化
     * @return array
     */
    abstract public function arrayify(): array;
}
