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
    /** @var string 路径内容分隔符 */
    protected const PATH_SEPARATOR = ';';

    /** @var string 路径与请求ID分隔符 */
    protected const REQUEST_SEPARATOR = ':';

    /** @var int 连接描述符 */
    protected int $fd;

    /** @var int|null 请求响应模式的请求ID */
    protected int|null $requestId;

    /** @var array|null 解析出的数据 */
    protected array|null $dataParsed;

    /** @inheritDoc */
    public function routeGetNode(): Node {
        $nodeTree = NodeManager::getTreeByPath($this->path);
        ! $nodeTree && in_array($this->path, ['', '/']) && $nodeTree = NodeManager::getTreeByPath('dce/empty/connection');
        $node = $nodeTree ? $nodeTree->getFirstNode() : null;
        ! key_exists($this->method, $node->methods ?? []) && throw (new RequestException(RequestException::NODE_LOCATION_FAILED))->format($this->getClientInfo()['request']);
        $this->logRequest($node);
        return $node;
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        $clientInfo = $this->getServer()->getServer()->getClientInfo($this->fd);
        $clientInfo['request'] = "$this->method $this->path:" . ($this->requestId ?? '');
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
        // 此方法会在各个外部方法中被调用，所以需定义为静态方法，而请求响应式不会在外部调用，所以没必要在外部增加请求id参数，而是在子类实现response方法时直接在path上追加requestId
        return sprintf('%s%s', false === $path ? '' : $path . self::PATH_SEPARATOR, is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 拆包接收的数据
     * @param string $data
     * @return array
     */
    public static function unPack(string $data): array {
        $requestId = null;
        $data = explode(self::PATH_SEPARATOR, $data, 2);
        if (count($data) > 1) {
            $path = explode(self::REQUEST_SEPARATOR, $data[0], 2);
            count($path) > 1 && $requestId = $path[1];
            $path = $path[0];
            $data = $data[1];
        } else {
            $path = '';
            $data = $data[0];
        }
        $dataParsed = json_decode($data, true) ?: null;
        return [
            'path' => $path,
            'requestId' => $requestId,
            'data' => $data,
            'dataParsed' => $dataParsed,
        ];
    }

    /** 是否请求响应模式 */
    public function isResponseMode(): bool {
        return isset($this->requestId);
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
