<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/11 23:54
 */

namespace dce\project;

use dce\config\DceConfig;
use dce\config\ConfigManager;
use dce\project\node\Node;
use dce\project\node\NodeTree;

class Project {
    /** @var bool $isComplete 是否已完善 */
    public bool $isComplete = false;

    /** @var bool 是否系统项目（内置项目） */
    public bool $isSystematic = false;

    public NodeTree $nodeTree;

    public DceConfig $config;

    public DceConfig $pureConfig;

    public array $extra = [];

    public function __construct(
        public string $name,
        public string $path,
    ) {
        str_starts_with($this->path, ProjectManager::$systemProjectRoot) && $this->isSystematic = true;
    }

    public function setNodeTree(NodeTree $nodeTree) {
        $this->nodeTree = $nodeTree;
    }

    /**
     * 取项目配置, 若配置为初始化过, 则先初始化
     * @return DceConfig
     */
    public function getConfig(): DceConfig {
        if (! isset($this->config)) {
            $this->config = ConfigManager::getProjectConfig($this);
        }
        return $this->config;
    }

    /**
     * 取未合并公共配置的项目配置
     * @return DceConfig
     */
    public function getPureConfig(): DceConfig {
        if (! isset($this->pureConfig)) {
            $this->pureConfig = ConfigManager::getPureProjectConfig($this);
        }
        return $this->pureConfig;
    }

    /**
     * 取项目根节点
     * @return Node
     */
    public function getRootNode(): Node {
        return $this->nodeTree->getFirstNode();
    }

    /**
     * 扩展属性
     * @param string $key
     * @param mixed $value
     */
    public function extendProperty(string $key, mixed $value) {
        $this->extra[$key] = $value;
    }
}
