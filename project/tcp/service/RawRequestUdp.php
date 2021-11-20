<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/28 22:08
 */

namespace tcp\service;

use dce\log\LogManager;
use dce\project\request\Request;
use dce\service\server\RawRequestConnection;
use dce\service\server\ServerMatrix;

class RawRequestUdp extends RawRequestConnection {
    public string $method = 'udp';

    public function __construct(
        private ServerMatrix $server,
        string $packet,
        private array $clientInfo,
    ) {
        $this->clientInfo['ip'] = $this->clientInfo['address'];
        $this->clientInfo['packet'] = $packet;
    }

    /** @inheritDoc */
    public function getServer(): ServerMatrix {
        return $this->server;
    }

    /** @inheritDoc */
    public function getRaw(): array {
        return $this->clientInfo;
    }

    /** @inheritDoc */
    public function init(): void {
        ['path' => $this->path, 'requestId' => $this->requestId, 'data' => $this->rawData, 'dataParsed' => $this->dataParsed] = $this->unPack($this->clientInfo['packet']);
    }

    /** @inheritDoc */
    public function supplementRequest(Request $request): void {
        $request->rawData = $this->rawData;
        $request->request = is_array($this->dataParsed) ? $this->dataParsed : [];
    }

    /** @inheritDoc */
    public function response(mixed $data, string|false $path): bool {
        LogManager::response($this, $data);
        return $this->server->sendTo($this->clientInfo['address'], $this->clientInfo['port'], $data, $path . (isset($this->requestId) ? self::REQUEST_SEPARATOR . $this->requestId : ''));
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        $this->clientInfo['request'] = "$this->method {$this->clientInfo['ip']}/$this->path:" . ($this->requestId ?? '');
        return $this->clientInfo;
    }
}
