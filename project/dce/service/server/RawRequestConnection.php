<?php
/**
 * Author: Drunk
 * Date: 2020-04-29 17:01
 */

namespace dce\service\server;

use dce\project\node\Node;
use dce\project\node\NodeManager;
use dce\project\request\RawRequest;
use dce\project\request\RequestException;

abstract class RawRequestConnection extends RawRequest {
    /** @var int 连接描述符 */
    protected int $fd;

    /** @var array|null 解析出的数据 */
    protected array|null $dataParsed;

    /** @inheritDoc */
    public function routeGetNode(): Node {
        $nodeTree = NodeManager::getTreeByPath($this->path);
        if (! $nodeTree) {
            if (! in_array($this->path, ['', '/'])) {
                throw new RequestException("{$this->path} 节点不存在");
            }
            // 如果未匹配到节点，且请求路径为空，则重定向到空的长连接节点
            $nodeTree = NodeManager::getTreeByPath('dce/empty/connection');
        }
        $node = $nodeTree->getFirstNode();
        return $node;
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        $clientInfo = $this->getServer()->getServer()->getClientInfo($this->fd);
        $clientInfo['ip'] = $clientInfo['remote_ip'];
        $clientInfo['port'] = $clientInfo['remote_port'];
        return $clientInfo;
    }

    /**
     * 打包待推数据
     * @param string|false $path
     * @param mixed $data
     * @return string
     */
    public static function pack(string|false $path, mixed $data): string {
        return sprintf('%s%s', false === $path ? '' : $path . "\n", json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 拆包接收的数据
     * @param string $data
     * @return array
     */
    public static function unPack(string $data): array {
        $data = explode("\n", $data, 2);
        if (count($data) > 1) {
            $path = $data[0];
            $data = $data[1];
        } else {
            $path = '';
            $data = $data[0];
        }
        $dataParsed = json_decode($data, true) ?: null;
        return [
            'path' => $path,
            'data' => $data,
            'dataParsed' => $dataParsed,
        ];
    }

    /**
     * 取Server对象, 子类实现返回具体服务器类型
     * @return ServerMatrix
     */
    abstract public function getServer(): ServerMatrix;

    /**
     * 响应客户端, 回发数据
     * @param mixed $data
     * @param string|false $path
     * @return bool
     */
    abstract public function response(mixed $data, string|false $path): bool;
}
