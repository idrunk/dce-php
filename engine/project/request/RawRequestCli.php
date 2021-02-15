<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/12 4:48
 */

namespace dce\project\request;

use dce\project\node\Node;
use dce\project\node\NodeManager;

class RawRequestCli extends RawRequest {
    private array $pureCli = [];

    private array $cli = [];

    /** @inheritDoc */
    public function getRaw(): array {
        global $argv;
        return $argv;
    }

    /** @inheritDoc */
    public function init(): void {
        $this->method = 'cli';
        $this->path = $this->makeQueryPath();
    }

    /**
     * 将命令行命令路径组装为通用形式
     * @return string
     * @throws RequestException
     */
    private function makeQueryPath(): string {
        global $argc, $argv;
        $this->rawData = array_slice($argv, 1);
        $paths = $this->rawData ? [] : [''];
        for ($i = 0; $i < $argc - 1; $i ++) {
            $argName = $arg = $this->rawData[$i];
            $argType = self::argType($arg);
            if ($argType || $this->cli) {
                $argValue = true;
                // 提取参数名与值
                if (1 === $argType) {
                    [$argName, $argValue] = explode('=', $arg, 2);
                } else if (2 === $argType) {
                    $nextArg = $this->rawData[$i + 1] ?? null;
                    if (! ($nextArgType = self::argType($nextArg))) {
                        $argValue = $nextArg ?? true;
                        $i ++;
                    }
                }

                // 处理数组式参数
                if (key_exists($argName, $this->cli) && true !== $argValue) {
                    if (! is_array($this->cli[$argName])) {
                        $this->cli[$argName] = [$this->cli[$argName]];
                    }
                    $this->cli[$argName][] = $argValue;
                } else {
                    $this->cli[$argName] = $argValue;
                }
                $argNamePure = ltrim($argName, '-');
                if (key_exists($argNamePure, $this->pureCli) && true !== $argValue) {
                    if (! is_array($this->pureCli[$argNamePure])) {
                        $this->pureCli[$argNamePure] = [$this->pureCli[$argNamePure]];
                    }
                    $this->pureCli[$argNamePure][] = $argValue;
                } else {
                    $this->pureCli[$argNamePure] = $argValue;
                }
            } else {
                $paths[] = $arg;
            }
        }
        return implode('/', $paths);
    }

    /**
     * 判断参数类型
     * @param string|null $arg
     * @return int|null {null, 0, 1, 2}
     */
    private static function argType(string|null $arg): int|null {
        if (null === $arg) {
            return null;
        }
        return str_contains($arg, '=') ? 1 : (str_starts_with($arg, '-') ? 2 : 0);
    }

    /** @inheritDoc */
    public function routeGetNode(): Node {
        $router = new Router($this);
        // 取路由定位到的当前节点及其上级节点ID集
        $nodeIdFamily = $router->getLocatedNodeIdFamily();
        return NodeManager::getNode(end($nodeIdFamily));
    }

    /** @inheritDoc */
    public function supplementRequest(Request $request): void {
        $request->rawData = $this->rawData;
        $request->pureCli = $this->pureCli;
        $request->request = $this->cli;
        $request->cli = $this->cli;
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        return [];
    }
}
