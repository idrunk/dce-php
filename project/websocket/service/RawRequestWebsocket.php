<?php
/**
 * Author: Drunk
 * Date: 2020-04-24 16:31
 */

namespace websocket\service;

use dce\Dce;
use dce\project\request\Request;
use dce\service\server\RawRequestConnection;
use Swoole\WebSocket\Frame;

class RawRequestWebsocket extends RawRequestConnection {
    private WebsocketServer $websocketServer;

    private Frame $frame;

    /** @var array Websocket的cookie是在open时缓存的, 在请求中是只读的 */
    public array $cookie;

    public function __construct(WebsocketServer $server, Frame $frame) {
        $this->websocketServer = $server;
        $this->frame = $frame;
    }

    /**
     * 取Websocket Service
     * @return WebsocketServer
     */
    public function getServer(): WebsocketServer {
        return $this->websocketServer;
    }

    /** @inheritDoc */
    public function getRaw(): Frame {
        return $this->frame;
    }

    /** @inheritDoc */
    public function init(): void {
        $this->method = 'websocket';
        ['path' => $path, 'data' => $rawData, 'dataParsed' => $dataParsed] = $this->unPack($this->frame->data);
        $this->path = $path;
        $this->rawData = $rawData;
        $this->dataParsed = $dataParsed;
    }

    /** @inheritDoc */
    public function supplementRequest(Request $request): void {
        $request->fd = $this->fd = $this->frame->fd;
        $request->rawData = $this->rawData;
        if (is_array($this->dataParsed)) {
            $request->request = $this->dataParsed;
        }
        $this->cookie = Dce::$cache->var->get(['cookie', $request->fd]) ?: [];
        $request->session = Dce::$cache->var->get(['session', $request->fd]);
    }

    /** @inheritDoc */
    public function response(mixed $data, string|false $path): bool {
        return $this->websocketServer->push($this->frame->fd, $data, $path);
    }
}
