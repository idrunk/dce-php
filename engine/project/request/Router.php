<?php
/**
 * Author: Drunk
 * Date: 2016-12-1 23:46
 */

namespace dce\project\request;

use dce\project\node\Node;
use dce\project\node\NodeArgument;
use dce\project\node\NodeManager;
use dce\project\node\NodeTree;
use drunk\Char;

class Router {
    private RawRequest $rawRequest;

    private string|null $urlSuffix;

    private array $locatedNodeIdFamily;

    private array $locatedArguments;

    private array $componentsRemaining;

    /**
     * Router constructor.
     * @param RawRequest $rawRequest
     * @throws RouterException
     */
    public function __construct(RawRequest $rawRequest) {
        $this->rawRequest = $rawRequest;
        $components = $this->parseComponents();
        $this->location(NodeManager::getRootTree(), $components);
    }

    /**
     * 取匹配到的节点ID集
     * @return array
     */
    public function getLocatedNodeIdFamily(): array {
        return $this->locatedNodeIdFamily;
    }

    /**
     * 取提取出的get参数集
     * @return array
     */
    public function getLocatedArguments(): array {
        return $this->locatedArguments;
    }

    /**
     * 取惰性匹配时剩余未处理的组件
     * @return array
     */
    public function getComponentsRemaining(): array {
        return $this->componentsRemaining;
    }

    /**
     * 拆解url为路由组件
     */
    private function parseComponents(): array {
        [$queryPath] = explode('&', Char::gbToUtf8($this->rawRequest->path), 2);
        preg_match('/(?:\.\w+|\/)$/', $queryPath, $suffix);
        $this->urlSuffix = $suffix[0] ?? '';
        $components = [];
        if (preg_match_all('/([\/-]|^)([^\/\\\.\s&-]+)/', $queryPath, $matches, PREG_SET_ORDER)) {
            // 拆解url提取组件, 如: /home/news-1 -> [['/', 'home'], ['/', 'news'], ['-', 1]]
            foreach ($matches as $v) {
                $components[] = ['separator' => $v[1], 'argument' => Url::argumentDecode($v[2])];
            }
        }
        return $components;
    }

    /**
     * 根据url路由定位当前访问节点
     * @param NodeTree $nodeTree
     * @param array $components
     * @param array $nodeIdElders
     * @param array $urlArguments
     * @return bool|null
     * @throws RouterException
     */
    private function location(NodeTree $nodeTree, array $components, array $nodeIdElders = [], array $urlArguments = []) {
        // 取已匹配到的父路径, 用于后续递归匹配
        $pathParent = self::locationPathByIds($nodeIdElders);
        $isProjectLevel = $pathParent === '';
        if ($nodeTree->isEmpty()) {
            if ($isProjectLevel) {
                // 如果根级下无子节点, 则表示未定义任何节点配置, 则无法路由定位
                throw new RouterException(RouterException::PROJECT_NO_CHILD);
            } else {
                return false;
            }
        }

        // 根据Path匹配定位Node
        $nodeTrees = self::locationByPath($nodeTree, $components, $pathParent);
        if (! $nodeTrees) {
            if ($isProjectLevel) {
                // 如果在根级别定位失败, 则最终定位失败
                throw new RouterException(RouterException::PROJECT_LOCATION_FAILED);
            } else {
                return false;
            }
        }

        // 根据Path参数匹配定位Node
        $isLocated = $this->locationByArguments($nodeTrees, $components, $nodeIdElders, $urlArguments);
        if (! $isProjectLevel) {
            // 若非根级别, 则直接返回匹配结果
            return $isLocated;
        }
        if ($isLocated !== true) {
            // 如果在根级别定位失败, 则最终定位失败
            throw new RouterException(RouterException::NODE_LOCATION_FAILED);
        } else {
            return true;
        }
    }

    /**
     * 取父节点路径
     * @param array $nodeIdElders
     * @return string
     */
    private static function locationPathByIds(array $nodeIdElders): string {
        $path_parent = '';
        if ($nodeIdElders) {
            $nodeTree = NodeManager::getTreeById(end($nodeIdElders));
            $path_parent = $nodeTree->pathFormat .'/';
        }
        return $path_parent;
    }

    /**
     * 根据url路径组件匹配节点集
     * @param NodeTree $nodeTree
     * @param array $components
     * @param string $pathParent
     * @return array
     */
    private static function locationByPath(NodeTree $nodeTree, array & $components, string $pathParent): array {
        $component = self::componentTake($components, 1);
        $pathFormat = $pathParent . ($component[0]['argument'] ?? '');
        if ($nodeTree->has($pathFormat)) {
            // 命中常规节点
            $nodeTrees[] = $nodeTree->getChild($pathFormat);
            self::componentTakeLock($components, 1);
        } else {
            // 未命中常规节点时, 收集可省略节点
            $nodeTrees = array_values($nodeTree->hiddenChildren);
        }
        return $nodeTrees;
    }

    /**
     * 根据url参数匹配节点
     * @param NodeTree[] $nodeTrees
     * @param array $components
     * @param array $nodeIdElders
     * @param array $urlArguments
     * @return bool|null
     * @throws RouterException
     */
    private function locationByArguments(array $nodeTrees, array $components, array $nodeIdElders, array $urlArguments): bool|null {
        foreach ($nodeTrees as $nodeTree) {
            foreach ($nodeTree->nodes as $node) {
                $componentsRemaining = $components;
                // 若未指定请求类型, 则不对请求类型做限制, 只要url匹配到即可
                // 若非目录型节点, 则当前的请求类型必须符合节点配置
                $methodMatched = in_array($this->rawRequest->method, $node->methods);
                if (! $methodMatched && ! $node->isDir) {
                    continue;
                }
                $gottenArguments = self::locationMatchArguments($node, $componentsRemaining);
                if ($gottenArguments === false) {
                    // 如果参数不匹配, 则跳过
                    continue;
                }
                $gottenArguments = array_merge($urlArguments, $gottenArguments);
                $nodeIdElders[] = $node->id;
                if ($node->lazyMatch) {
                    // 如果节点配置了锁定, 则不再尝试继续递归, 节点定位完毕
                    return $this->locationDone($nodeIdElders, $gottenArguments, $componentsRemaining);
                }
                // 当前节点元素是否最后一个, (Url组件是否已经处理完毕)
                $itemIsLast = self::isComponentTakeDone($componentsRemaining);
                if ($methodMatched && $itemIsLast && in_array($this->urlSuffix, $node->urlSuffix)) {
                    // DCE3.1去掉了默认文档支持, 可以配置多个相同控制器节点实现默认文档功能
                    // 如果组件处理完毕, 且后缀匹配, 则表示定位完毕
                    return $this->locationDone($nodeIdElders, $gottenArguments);
                } else {
                    $location = $this->location($nodeTree, $componentsRemaining, $nodeIdElders, $gottenArguments);
                    if ($location === true) {
                        return true;
                    }
                }
            }
        }
        return null;
    }

    /**
     * 匹配节点项参数
     * @param Node $node
     * @param array $components
     * @return array|bool
     * @throws RouterException
     */
    private static function locationMatchArguments(Node $node, array & $components) {
        $gottenArguments = [];
        if (empty($node->urlArguments)) {
            // 如果未配置参数, 则直接返回
            return $gottenArguments;
        }
        $cntComponentTake = count($node->urlArguments);
        $argumentsPossible = self::componentTake($components, $cntComponentTake);
        if ($node->urlPlaceholder) {
            // 保留分隔符模式下匹配参数
            foreach ($node->urlArguments as $k => $argument) {
                $needMatchSeparator = $k || ! $node->omissiblePath;
                $gottenArguments[$argument->name] = self::parseMatchArgument($argumentsPossible[$k], $argument, $gottenArguments, $needMatchSeparator);
                if (false === $gottenArguments[$argument->name]) {
                    return false;
                }
                if (empty($gottenArguments[$argument->name])) {
                    unset($gottenArguments[$argument->name]);
                }
            }
        } else {
            // 不保留分隔符模式下匹配参数
            $keyLog = [];
            foreach ($node->urlArguments as $i => $argument) {
                $filled = false;
                foreach ($argumentsPossible as $k => $v) {
                    if (array_key_exists($k, $keyLog)) {
                        continue;
                    }
                    $needMatchSeparator = $k || ! $node->omissiblePath;
                    $gottenArguments[$argument->name] = self::parseMatchArgument($argumentsPossible[$i], $argument, $gottenArguments, $needMatchSeparator);
                    if (! empty($gottenArguments[$argument->name])) {
                        $filled = true;
                        $keyLog[$k] = 1;
                        break;
                    }
                }
                if ($argument->required && ! $filled) {
                    return false;
                }
            }
        }
        self::componentTakeLock($components, $cntComponentTake);
        return $gottenArguments;
    }

    /**
     * 匹配参数
     * @param array $param
     * @param NodeArgument $nodeArgument
     * @param array $getArguments
     * @param bool $needMatchSeparator
     * @return bool|false|mixed|string|null
     * @throws RouterException
     */
    private static function parseMatchArgument(array $param, NodeArgument $nodeArgument, array $getArguments, bool $needMatchSeparator) {
        if ($needMatchSeparator && ! is_null($param) && $param['separator'] !== $nodeArgument->separator ) {
            // 如果需匹配分隔符却不匹配, 则匹配失败
            return false;
        }
        if (is_null($param) || $param['argument'] === '') {
            // 如果参数为空, 如果必填, 则返回假, 否则返回null
            return $nodeArgument->required ? false : null; // is required // 是否必传
        }
        $argument = $param['argument'];
        if ($nodeArgument->prefix) {
            $lenPrefix = strlen($nodeArgument->prefix);
            $prefix = substr($argument, 0, $lenPrefix);
            if ($prefix !== $nodeArgument->prefix) {
                // 如果配置了前缀, 但不匹配, 则返回假
                return false;
            }
            // 提取除开前缀后的参数
            $argument = substr($argument, $lenPrefix);
        }
        $match = $nodeArgument->match ?? null;
        if (null === $match) {
            // 如果未定义匹配方法, 则直接返回参数
            return $argument;
        } else if (Char::isRegexp($match)) {
            // 如果定义了正则匹配, 则若匹配成功则返回参数, 否则返回假
            return preg_match($match, $argument) ? $argument : false;
        } else if (is_array($match)) {
            // 如果结果为数组, 则表示参数需为该数组元素, 否则返回假
            return in_array($argument, $match) ? $argument : false;
        } else if (function_exists($match)) {
            // 如果定义的是匹配方法, 则返回执行该方法的结果, (该匹配方法返回字符串参数值或假, 为假则表示参数匹配失败, 否则为匹配到的参数)
            return call_user_func($match, $argument, $getArguments);
        } else {
            throw new RouterException(RouterException::NODE_CONFIG_ERROR);
        }
    }

    /**
     * 取部分Url组件
     * @param array $components
     * @param int $cnt
     * @return array
     */
    private static function componentTake(array $components, int $cnt): array {
        return array_slice($components, 0, $cnt);
    }

    /**
     * 锁定Url组件 (截掉)
     * @param array $components
     * @param int $cnt
     */
    private static function componentTakeLock(array & $components, int $cnt): void {
        array_splice($components, 0, $cnt);
    }

    /**
     * 判断组件是否取完
     * @param array $components
     * @return bool
     */
    private static function isComponentTakeDone(array $components): bool {
        return empty($components);
    }

    /**
     * 节点路由定位完毕
     * @param array $nodeIdElders
     * @param array $extractedArguments
     * @param array $componentsRemaining
     * @return bool
     */
    private function locationDone(array $nodeIdElders, array $extractedArguments, array $componentsRemaining = []): bool {
        $this->locatedNodeIdFamily = $nodeIdElders;
        $this->locatedArguments = $extractedArguments;
        $this->componentsRemaining = array_column($componentsRemaining, 'argument');
        return true;
    }
}
