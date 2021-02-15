<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/14 1:20
 */

namespace dce\project\node;

use dce\base\TraitModel;
use drunk\Tree;

class NodeTree extends Tree {
    use TraitModel {
        TraitModel::arrayify as baseArrayify;
    }

    public string $projectName;

    public string $pathName;

    public string $pathFormat;

    public string $pathParent;

    /** @var Node[] 一个路径可能匹配不同的节点, 所以将节点按路径分组, (如将/edit同时用于添加编辑) */
    public array $nodes = [];

    /** @var NodeTree[] 隐藏路径子树集 */
    public array $hiddenChildren = [];

    public function __construct(array $properties) {
        $this->setProperties($properties);
    }

    /**
     * 添加节点
     * @param Node $node
     * @param string|null $key
     */
    public function addNode(Node $node, string|null $key = null): void {
        null === $key
            ? $this->nodes[] = $node
            : $this->nodes[$key] = $node;
    }

    /**
     * 根据ID取节点
     * @param string $id
     * @return Node
     */
    public function getNode(string $id): Node|null {
        return $this->nodes[$id] ?? null;
    }

    /**
     * 取第一个节点
     * @return Node
     */
    public function getFirstNode(): Node {
        return reset($this->nodes);
    }

    /**
     * 添加隐藏路径子树
     * @param Tree $tree
     * @param string|null $key
     */
    public function addHiddenChild(Tree $tree, string|null $key = null): void {
        null === $key
            ? $this->hiddenChildren[] = $tree
            : $this->hiddenChildren[$key] = $tree;
    }

    /**
     * 判断是否有隐藏路径子树
     * @param string $key
     * @return bool
     */
    public function hasHiddenChild(string $key): bool {
        return key_exists($key, $this->hiddenChildren);
    }

    /**
     * 将树对象数组化
     * @return array
     */
    public function arrayify(): array {
        $properties = $this->baseArrayify();
        $properties['nodes'] = $this->nodesArrayify();
        $properties['children'] = $this->childrenArrayify();
        $properties['hiddenChildren'] = $this->hiddenChildrenArrayify();
        return $properties;
    }

    /**
     * 将节点集属性数组化
     * @return array
     */
    private function nodesArrayify(): array {
        $children = [];
        foreach ($this->nodes as $child) {
            $children[] = $child->arrayify();
        }
        return $children;
    }

    /**
     * 将隐藏路径子树集数组化
     * @return array
     */
    private function hiddenChildrenArrayify(): array {
        $children = [];
        foreach ($this->hiddenChildren as $child) {
            $children[] = $child->arrayify();
        }
        return $children;
    }
}
