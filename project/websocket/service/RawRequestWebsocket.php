<?php
/**
 * Author: Drunk
 * Date: 2020-04-24 16:31
 */

namespace websocket\service;

use dce\log\LogManager;
use dce\project\request\Request;
use dce\service\server\Connection;
use dce\service\server\RawRequestConnection;
use Swoole\WebSocket\Frame;

class RawRequestWebsocket extends RawRequestConnection {
    public const ConnectionPath = 'websocket/connecting';

    public string $method = 'websocket';

    /** @var array Websocket的cookie是在open时缓存的, 在请求中是只读的 */
    public array $cookie;

    private Frame $frame;

    public function __construct(
        private WebsocketServer $server,
        Frame|Connection $frame,
    ) {
        if ($frame instanceof Connection) {
            $this->isConnecting = true;
            $this->connection = $frame;
        } else {
            $this->frame = $frame;
            $this->connection = Connection::from($frame->fd);
        }
        $this->fd = $frame->fd;
    }

    /**
     * 取Websocket Service
     * @return WebsocketServer
     */
    public function getServer(): WebsocketServer {
        return $this->server;
    }

    /** @return Frame */
    public function getRaw(): mixed {
        return $this->frame;
    }

    /** @inheritDoc */
    public function init(): void {
        ['path' => $this->path, 'requestId' => $this->requestId, 'data' => $this->rawData, 'dataParsed' => $this->dataParsed] = $this->isConnecting
            ? ['path' => $this->connection->initialNode->pathFormat, 'requestId' => null, 'data' => $this->connection->swRequest->server['query_string'], 'dataParsed' => $this->connection->swRequest->get]
            : $this->unPack($this->frame->data);
    }

    /** @inheritDoc */
    public function supplementRequest(Request $request): void {
        $request->fd = $this->fd;
        $request->rawData = $this->rawData;
        $request->request = is_array($this->dataParsed) ? $this->dataParsed : [];
        $request->session = $this->connection->session;
        $this->cookie = $this->connection->swRequest->cookie ?? [];
    }

    /** @inheritDoc */
    public function response(mixed $data, string|false $path): bool {
        LogManager::response($this, $data);
        return $this->getServer()->push($this->fd, $data, $path . (isset($this->requestId) ? self::REQUEST_SEPARATOR . $this->requestId : ''));
    }
}
