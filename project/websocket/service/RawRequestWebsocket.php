<?php
/**
 * Author: Drunk
 * Date: 2020-04-24 16:31
 */

namespace websocket\service;

use dce\Dce;
use dce\log\LogManager;
use dce\project\request\Request;
use dce\service\server\RawRequestConnection;
use Swoole\WebSocket\Frame;

class RawRequestWebsocket extends RawRequestConnection {
    public string $method = 'websocket';

    /** @var array Websocket的cookie是在open时缓存的, 在请求中是只读的 */
    public array $cookie;

    public function __construct(
        private WebsocketServer $server,
        private Frame $frame,
    ) {
        $this->fd = $this->frame->fd;
    }

    /**
     * 取Websocket Service
     * @return WebsocketServer
     */
    public function getServer(): WebsocketServer {
        return $this->server;
    }

    /** @inheritDoc */
    public function getRaw(): Frame {
        return $this->frame;
    }

    /** @inheritDoc */
    public function init(): void {
        ['path' => $this->path, 'requestId' => $this->requestId, 'data' => $this->rawData, 'dataParsed' => $this->dataParsed] = $this->unPack($this->frame->data);
    }

    /** @inheritDoc */
    public function supplementRequest(Request $request): void {
        $request->fd = $this->fd;
        $request->rawData = $this->rawData;
        if (is_array($this->dataParsed)) {
            $request->request = $this->dataParsed;
        }
        $this->cookie = Dce::$cache->var->get(['cookie', $request->fd]) ?: [];
        $request->session = Dce::$cache->var->get(['session', $request->fd]);
    }

    /** @inheritDoc */
    public function response(mixed $data, string|false $path): bool {
        LogManager::response($this, $data);
        return $this->getServer()->push($this->fd, $data, $path . (isset($this->requestId) ? self::REQUEST_SEPARATOR . $this->requestId : ''));
    }
}
