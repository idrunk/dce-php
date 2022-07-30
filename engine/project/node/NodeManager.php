<?php
/**
 * Author: Drunk
 * Date: 2016-12-1 23:47
 */

namespace dce\project\node;

use dce\Dce;
use dce\project\Project;
use dce\project\ProjectManager;
use dce\project\render\Renderer;
use drunk\Structure;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final class NodeManager {
    /** @var NodeTree|null 所有项目根节点树 */
    private static NodeTree|null $nodeTree;

    /** @var array 路径节点树映射表 */
    private static array $pathTreeMapping;

    /** @var array ID节点树映射表 */
    private static array $idTreeMapping;

    /**
     * 扫描并初始化所有项目节点数据
     */
    public static function scanInit(): void {
        // 一般情况下都是从缓存取, 所以在这直接先尝试取, 不影响性能, 降低逻辑复杂度
        self::$nodeTree = Dce::$cache->get('dce_node_tree') ?: null;
        self::$pathTreeMapping = Dce::$cache->get('dce_path_tree_mapping') ?: [];
        self::$idTreeMapping = Dce::$cache->get('dce_id_tree_mapping') ?: [];

        // 如果缓存有效且开启了节点缓存, 则直接返回
        if (self::$idTreeMapping && Dce::$config->node['cache']) return;
        $nodesFiles = $nodesFileList = [];
        foreach (ProjectManager::getAll() as $project) {
            if (is_file($nodesFile = "{$project->path}config/nodes.php")) {
                $nodesFileList[] = $nodesFiles[$project->name] = $nodesFile;
            } else {
                $nodesFiles[$project->name] = self::listControllerFiles($project);
                $nodesFileList = array_merge($nodesFileList, $nodesFiles[$project->name]);
            }
        }
        // 如果文件无变化, 则返回
        if (self::$idTreeMapping && ! Dce::$cache::fileIsModified($nodesFileList)) return;

        $rootTree = new NodeTree([
            'node_name' => 'root',
            'path_format' => '',
        ]);
        // 从全部项目加载节点配置
        foreach ($nodesFiles as $projectName => $nodesFile) {
            // 文件为数组, 则表示是控制器文件集, 则应以注解Node解析Nodes, 否则直接加载Nodes数组
            $nodes = is_array($nodesFile) ? self::parseAttrNodes($nodesFile) : include($nodesFile);
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
     * 列出项目控制器文件集
     * @param Project $project
     * @return array
     */
    private static function listControllerFiles(Project $project): array {
        $files = [];
        for ($i = 0; $i < Dce::$config->node['deep']; $i ++)
            // GLOB_BRACE 在某些操作系统无效, 所以递归来实现吧
            $files = array_merge($files, glob("{$project->path}controller/*".str_repeat('/*', $i).'.php') ?: []);
        return $files;
    }

    /**
     * 尝试从控制器文件解析AttrNode提取Node集
     * @param array $controllerFiles
     * @return array
     * @throws ReflectionException
     */
    private static function parseAttrNodes(array $controllerFiles): array {
        $nodes = [];
        foreach ($controllerFiles as $file) {
            if (! preg_match('/(\w+)\/controller\/.*?(?=.php$)/ui', $file, $className))
                continue;
            $controllerPath = '';
            $fileNodesOffset = count($nodes);
            [$className, $projectName] = $className;
            $className = '\\' . str_replace('/', '\\', $className);
            $refClass = new ReflectionClass($className);
            foreach (array_filter($refClass->getMethods(ReflectionMethod::IS_PUBLIC), fn($m) => $m->class === $refClass->name) as $method) {
                foreach ($method->getAttributes(Node::class) as $attribute) {
                    $nodes[] = $node = Node::refToNodeArguments($method, $attribute);
                    if ($node['controller_path'] ?? false) {
                        unset($node['controller_path']);
                        $controllerPath = $node['path'] ?? '';
                    }
                }
            }
            for ($i = count($nodes) - 1; $i >= $fileNodesOffset; $i --)
                Node::fillControllerNodePath($nodes[$i], $controllerPath, $projectName);
        }
        return $nodes;
    }

    /**
     * 自动补全父辈节点, 如仅配置了news/sports/football, 未配置news, news/sports节点, 则会自动补上此两节点
     * @param array $nodes
     * @return array
     */
    private static function initFillElder(array $nodes): array {
        // 取出路径集并反转为以之为下标的数组
        $paths = array_flip(array_column($nodes, 'path'));
        foreach ($paths as $path => $v) {
            $elderParts = explode('/', $path, -1);
            // 若无父辈, 则无需继续补全
            if (! $elderParts) continue;
            $elderPath = '';
            foreach ($elderParts as $part) {
                $elderPath .= ($elderPath === '' ? '' : '/') . $part;
                // 若该父辈存在, 则无需补全
                if (key_exists($elderPath, $paths)) continue;
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
     * @throws NodeException
     */
    private static function initTree(array $nodes, NodeTree $rootTree, string $projectName): void {
        $hasRoot = 0;
        // 节点树列表, 供后续做树形化计算
        $nodeTrees = [];
        foreach ($nodes as $v) {
            $node = new Node($v, $projectName);
            $node->path === $projectName && $hasRoot = 1;
            $pathInfo = $node->genPathInfo();
            $nodeTrees[$node->pathFormat] = $nodeTree = $nodeTrees[$node->pathFormat] ?? new NodeTree($pathInfo);
            $nodeTree->addNode($node, $node->id);
        }
        // 如果未定义项目根节点, 则定义
        ! $hasRoot && $nodeTrees[$projectName] = self::initRootTree($projectName);
        $rootTrees = [$rootTree];
        while ($parentTree = array_pop($rootTrees)) {
            foreach ($nodeTrees as $path => $nodeTree) {
                if ($nodeTree->pathParent === $parentTree->pathFormat) {
                    // 拼装树形结构
                    $parentTree->setChild($nodeTree, $path);
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
    private static function initRootTree(string $projectName): NodeTree {
        $rootNode = new Node(['path' => $projectName], $projectName);
        $rootNodeTree = new NodeTree($rootNode->genPathInfo());
        $rootNodeTree->addNode($rootNode, $rootNode->id);
        return $rootNodeTree;
    }

    /**
     * 根据树形化结构补全的节点属性
     * @param NodeTree $nodeTree
     */
    private static function initTreeItem(NodeTree $nodeTree): void {
        $nodeTree->traversal(function(NodeTree $child) {
            $isDir = !! $child->children;
            $familyPaths = $child->getParentIds();

            // 记录路径索引
            self::$pathTreeMapping[$child->pathFormat] = $familyPaths;
            $parentNode = $child->parent->nodes[key($child->parent->nodes)] ?? null;
            foreach($child->nodes as $node) {
                // 记录ID索引
                self::$idTreeMapping[$node->id] = $familyPaths;
                // 如果节点为隐藏路径节点, 且父树中未记录该节点, 则添加到父树隐藏路径节点集中
                $node->omissiblePath && ! $child->parent->hasHiddenChild($child->pathFormat) && $child->parent->addHiddenChild($child, $child->pathFormat);

                ! isset($node->corsOrigins) && $node->corsOrigins = $parentNode->corsOrigins ?? [];
                ! isset($node->methods) && $node->methods = $parentNode->methods ?? ['get' => null];
                if (! isset($node->render)) {
                    $node->render = isset($parentNode->render) && ! str_contains($parentNode->render, '.') ? $parentNode->render : Renderer::TYPE_JSON;
                } else if (! str_contains($node->render, '.')) {
                    // 对含有后缀名的大概非模板的自动转为小写
                    $node->render = strtolower($node->render);
                }
                ! isset($node->templateLayout) && $node->templateLayout = $parentNode->templateLayout ?? '';
                // 继承enableCoroutine属性, 或初始化为false, 即不自动开启协程容器
                ! isset($node->enableCoroutine) && $node->enableCoroutine = $parentNode->enableCoroutine ?? false;
                ! isset($node->extra) && $node->extra = $parentNode->extra ?? [];
                $node->isDir = $isDir;
            }
        });
    }

    /**
     * 取根节点树
     * @return NodeTree
     */
    public static function getRootTree(): NodeTree {
        return self::$nodeTree;
    }

    /**
     * 根据路径取节点树
     * @param string $path
     * @return NodeTree|null
     */
    public static function getTreeByPath(string $path): NodeTree|null {
        if (! key_exists($path, self::$pathTreeMapping)) return null;
        $paths = self::$pathTreeMapping[$path];
        return self::getTreeByPaths($paths);
    }

    /**
     * 根据节点ID取节点树
     * @param string $id
     * @return NodeTree|null
     */
    public static function getTreeById(string $id): NodeTree|null {
        if (! key_exists($id, self::$idTreeMapping)) return null;
        $paths = self::$idTreeMapping[$id];
        return self::getTreeByPaths($paths);
    }

    /**
     * 根据节点ID取节点对象
     * @param string $id
     * @return Node|null
     */
    public static function getNode(string $id): Node|null {
        $nodeTree = self::getTreeById($id);
        return $nodeTree?->getNode($id);
    }

    /**
     * 根据关系节点集取节点树
     * @param array $paths
     * @return NodeTree
     */
    private static function getTreeByPaths(array $paths): NodeTree {
        $nodeTree = self::$nodeTree;
        foreach ($paths as $path)
            $nodeTree = $nodeTree->getChild($path);
        return $nodeTree;
    }

    /**
     * 判断某个节点路径是否在当前节点族中, 用于导航菜单的active
     * @param string $needlePath
     * @param string $elderPath
     * @return bool
     */
    public static function isSubOf(string $needlePath, string $elderPath): bool {
        $elderNodeTree = self::getTreeByPath($elderPath);
        return $elderNodeTree && in_array($needlePath, $elderNodeTree->getParentIds());
    }

    /**
     * 根据主机地址匹配节点树 (主用于对主机与项目绑定特性的支持)
     * @param string $host
     * @param int $port
     * @return NodeTree|null
     */
    public static function getTreeByHost(string $host, int $port): NodeTree|null {
        static $hostTreeMapping;
        if (null === $hostTreeMapping) {
            $hostTreeMapping = [];
            foreach (self::getRootTree()->children as $child) {
                foreach ($child->nodes as $node) {
                    foreach ($node->projectHosts ?? [] as $nodeHost)
                        $hostTreeMapping[sprintf('%s:%s', $nodeHost['host'] ?? 'any', $nodeHost['port'] ?? 'any')] = $child;
                }
            }
        }
        return $hostTreeMapping["any:$port"] ?? $hostTreeMapping["$host:any"] ?? null; // 端口或主机匹配即可，无需全匹配，实际意义不大
    }

    public static function exists(string $path, bool $tryFillRoot = true): NodeTree|null {
        $paths = [$path];
        $tryFillRoot && $paths = array_reduce(ProjectManager::getAll(false), fn($ps, $p) => ["$p->name/$path", ... $ps], $paths);
        $pathIndex = Structure::arraySearch(fn($p) => key_exists($p, self::$pathTreeMapping), $paths);
        return $pathIndex === false ? null : self::getTreeByPath($paths[$pathIndex]);
    }
}
