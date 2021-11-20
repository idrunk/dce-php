<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/28 22:08
 */

namespace tcp\service;

use dce\log\LogManager;
use dce\project\request\Request;
use dce\service\server\Connection;
use dce\service\server\RawRequestConnection;
use dce\service\server\ServerMatrix;

class RawRequestTcp extends RawRequestConnection {
    public const ConnectionPath = 'tcp/connecting';

    public string $method = 'tcp';

    private array $raw;

    public function __construct(
        private ServerMatrix $server,
        string|Connection $data,
        protected int $fd,
        int $reactorId,
    ) {
        if ($data instanceof Connection) {
            $this->isConnecting = true;
            $this->connection = $data;
            $data = null;
        } else {
            $this->connection = Connection::from($fd);
        }
        $this->raw = [
            'fd' => $fd,
            'reactor_id' => $reactorId,
            'data' => $data,
        ];
    }

    /** @inheritDoc */
    public function getServer(): ServerMatrix {
        return  $this->server;
    }

    /** @inheritDoc */
    public function getRaw(): array {
        return $this->raw;
    }

    /** @inheritDoc */
    public function init(): void {
        ['path' => $this->path, 'requestId' => $this->requestId, 'data' => $this->rawData, 'dataParsed' => $this->dataParsed] = $this->isConnecting
            ? ['path' => $this->connection->initialNode->pathFormat, 'requestId' => null, 'data' => '', 'dataParsed' => null] : $this->unPack($this->raw['data']);
    }

    /** @inheritDoc */
    public function supplementRequest(Request $request): void {
        $request->fd = $this->fd;
        $request->rawData = $this->rawData;
        $request->request = is_array($this->dataParsed) ? $this->dataParsed : [];
        $request->session = $this->connection->session;
    }

    /** @inheritDoc */
    public function response(mixed $data, string|false $path): bool {
        LogManager::response($this, $data);
        return $this->server->send($this->raw['fd'], $data, $path . (isset($this->requestId) ? self::REQUEST_SEPARATOR . $this->requestId : ''));
    }
}
