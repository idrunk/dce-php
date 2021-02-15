<?php
/**
 * Author: Drunk
 * Date: 2016-12-1 23:47
 */

namespace dce\project\node;

use dce\Dce;
use dce\project\ProjectManager;

final class NodeManager {
    /**
     * 所有项目根节点树
     * @var NodeTree|null
     */
    private static NodeTree|null $nodeTree;

    /**
     * 路径节点树映射表
     * @var array|null
     */
    private static array|null $pathTreeMapping;

    /**
     * ID节点树映射表
     * @var array|null
     */
    private static array|null $idTreeMapping;

    /**
     * 扫描并初始化所有项目节点数据
     */
    public static function scanInit (): void {
        // 取全部项目节点配置文件
        $projects = ProjectManager::getAll();
        $nodesFiles = [];
        foreach ($projects as $k => $project) {
            if (is_file($nodesFile = "{$project->path}config/nodes.php")) {
                $nodesFiles[$project->name] = $nodesFile;
            }
        }
        $nodesWasModified = Dce::$cache::fileIsModified($nodesFiles);

        // 尝试从缓存加载节点配置
        if (! $nodesWasModified) {
            // 如果开启了缓存, 且缓存有效, 则从缓存中加载
            self::$nodeTree = Dce::$cache->get('dce_node_tree');
            self::$pathTreeMapping = Dce::$cache->get('dce_path_tree_mapping');
            self::$idTreeMapping = Dce::$cache->get('dce_id_tree_mapping') ?: [];
            if (! empty(self::$idTreeMapping)) {
                // 如果加载成功, 则返回
                return;
            }
        }

        // 从全部项目加载节点配置
        $rootTree = new NodeTree([
            'node_name' => 'root',
            'path_format' => '',
        ]);
        foreach ($nodesFiles as $projectName => $nodesFile) {
            $nodes =  include($nodesFile);
            $nodes = self::initFillElder($nodes);
            self::initTree($nodes, $rootTree, $projectName);
        }
        self::$nodeTree = $rootTree;

        // 缓存节点配置
        Dce::$cache->set('dce_node_tree', self::$nodeTree); // cache the nodes config
        Dce::$cache->set('dce_path_tree_mapping', self::$pathTreeMapping);
        Dce::$cache->set('dce_id_tree_mapping', self::$idTreeMapping);
    }

    /**
     * 自动补全父辈节点, 如仅配置了news/sports/football, 未配置news, news/sports节点, 则会自动补上此两节点
     * @param array $nodes
     * @return array
     */
    private static function initFillElder (array $nodes): array {
        // 取出路径集并反转为以之为下标的数组
        $paths = array_flip(array_column($nodes, 'path'));
        foreach ($paths as $path => $v) {
            $elderParts = explode('/', $path, -1);
            if (! $elderParts) {
                // 若无父辈, 则无需继续补全
                continue;
            }
            $elderPath = '';
            foreach ($elderParts as $part) {
                $elderPath .= ($elderPath === '' ? '' : '/') . $part;
                if (key_exists($elderPath, $paths)) {
                    // 若该父辈存在, 则无需补全
                    continue;
                }
                // 记录父辈入下标
                $paths[$elderPath] = 1;
                // 补到节点集
                $nodes[] = ['path' => $elderPath];
            }
        }
        return $nodes;
    }

    /**
     * 格式化节点数据并树形化
     * @param array $nodes
     * @param NodeTree $rootTree
     * @param string $projectName
     */
    private static function initTree (array $nodes, NodeTree $rootTree, string $projectName): void {
        $hasRoot = 0;
        // 节点树列表, 供后续做树形化计算
        $nodeTrees = [];
        foreach ($nodes as $k => $v) {
            $node = new Node($v, $projectName);
            if ($node->path === $projectName) {
                $hasRoot = 1;
            }
            $pathInfo = $node->genPathInfo();
            $nodeTrees[$node->pathFormat] = $nodeTree = $nodeTrees[$node->pathFormat] ?? new NodeTree($pathInfo);
            $nodeTree->addNode($node, $node->id);
        }
        if (! $hasRoot) {
            // 如果未定义项目根节点, 则定义
            $nodeTrees[$projectName] = self::initRootTree($projectName);
        }
        /**
         * 待寻子节点树集
         * @var NodeTree[] $rootTrees
         */
        $rootTrees = [$rootTree];
        while ($parentTree = array_pop($rootTrees)) {
            foreach ($nodeTrees as $path => $nodeTree) {
                if ($nodeTree->pathParent === $parentTree->pathFormat) {
                    // 拼装树形结构
                    $parentTree->addChild($nodeTree, $path);
                    $rootTrees[] = $nodeTree;
                    unset($nodes[$path]);
                }
            }
        }
        // 补充节点树属性
        self::initTreeItem($rootTree);
    }

    /**
     * 初始化树的根节点
     * @param string $projectName
     * @return NodeTree
     */
    private static function initRootTree (string $projectName): NodeTree {
        $rootNode = new Node(['path' => $projectName], $projectName);
        $rootNodeTree = new NodeTree($rootNode->genPathInfo());
        $rootNodeTree->addNode($rootNode, $rootNode->id);
        return $rootNodeTree;
    }

    /**
     * 根据树形化结构补全的节点属性
     * @param NodeTree $nodeTree
     */
    private static function initTreeItem (NodeTree $nodeTree): void {
        $nodeTree->traversal(function (NodeTree $child, NodeTree $parent) {
            $isDir = !! $child->children;
            $familyPaths = $child->getFamilyIds();
            // 记录路径索引
            self::$pathTreeMapping[$child->pathFormat] = $familyPaths;
            $parentNode = $parent->nodes[key($parent->nodes)] ?? null;
            foreach ($child->nodes as $i => $node) {
                // 记录ID索引
                self::$idTreeMapping[$node->id] = $familyPaths;
                if ($node->urlPathHidden && ! $parent->hasHiddenChild($child->pathFormat)) {
                    // 如果节点为隐藏路径节点, 且父树种未记录该节点, 则添加到父树隐藏路径节点集中
                    $parent->addHiddenChild($child, $child->pathFormat);
                }
                if (! isset($node->corsOrigins)) {
                    // 继承allowedOrigins属性, 或初始化为[]
                    $node->corsOrigins = $parentNode->corsOrigins ?? [];
                }
                if (! isset($node->methods)) {
                    // 继承method属性, 或初始化为get
                    $node->methods = $parentNode->methods ?? ['get', 'head'];
                }
                if (! isset($node->templateLayout)) {
                    // 继承templateLayout属性, 或初始化为'', 即不使用布局
                    $node->templateLayout = $parentNode->templateLayout ?? '';
                }
                if (! isset($node->enableCoroutine)) {
                    // 继承enableCoroutine属性, 或初始化为false, 即不自动开启协程容器
                    $node->enableCoroutine = $parentNode->enableCoroutine ?? false;
                }
                if (! isset($node->hookCoroutine)) {
                    // 继承hookCoroutine属性, 或根据enableCoroutine初始化
                    $node->hookCoroutine = $node->enableCoroutine;
                }
                $node->isDir = $isDir;
            }
        });
    }

    /**
     * 取根节点树
     * @return NodeTree
     */
    public static function getRootTree (): NodeTree {
        return self::$nodeTree;
    }

    /**
     * 根据路径取节点树
     * @param string $path
     * @return NodeTree|null
     */
    public static function getTreeByPath(string $path): NodeTree|null {
        if (! key_exists($path, self::$pathTreeMapping)) {
            return null;
        }
        $paths = self::$pathTreeMapping[$path];
        return self::getTreeByPaths($paths);
    }

    /**
     * 根据节点ID取节点树
     * @param string $id
     * @return NodeTree|null
     */
    public static function getTreeById(string $id): NodeTree|null {
        if (! key_exists($id, self::$idTreeMapping)) {
            return null;
        }
        $paths = self::$idTreeMapping[$id];
        return self::getTreeByPaths($paths);
    }

    /**
     * 根据节点ID取节点对象
     * @param string $id
     * @return Node
     */
    public static function getNode (string $id): Node|null {
        $nodeTree = self::getTreeById($id);
        if ($nodeTree) {
            return $nodeTree->getNode($id);
        }
        return null;
    }

    /**
     * 根据关系节点集取节点树
     * @param array $paths
     * @return NodeTree
     */
    private static function getTreeByPaths(array $paths): NodeTree {
        $nodeTree = self::$nodeTree;
        foreach ($paths as $path) {
            $nodeTree = $nodeTree->getChild($path);
        }
        return $nodeTree;
    }

    /**
     * 判断某个节点路径是否在当前节点族中, 用于导航菜单的active
     * @param string $needlePath
     * @param string $elderPath
     * @return bool
     */
    public static function isSubOf (string $needlePath, string $elderPath): bool {
        $elderNodeTree = self::getTreeByPath($elderPath);
        return $elderNodeTree
            ? in_array($needlePath, $elderNodeTree->getFamilyIds())
            : false;
    }

    /**
     * 根据主机地址匹配节点树 (主用于对主机与项目绑定特性的支持)
     * @param string $host
     * @return NodeTree|null
     */
    public static function getTreeByHost(string $host): NodeTree|null {
        static $hostTreeMapping;
        if (null === $hostTreeMapping) {
            $hostTreeMapping = [];
            /** @var NodeTree $child */
            foreach (self::getRootTree()->children as $child) {
                foreach ($child->nodes as $node) {
                    foreach ($node->projectHosts ?? [] as $nodeHost) {
                        $hostTreeMapping[$nodeHost] = $child;
                    }
                }
            }
        }
        return $hostTreeMapping[$host] ?? null;
    }
}
