<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/28 22:08
 */

namespace tcp\service;

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
        if (is_array($this->dataParsed)) {
            $request->request = $this->dataParsed;
        }
    }

    /** @inheritDoc */
    public function response(mixed $data, string|false $path): bool {
        return $this->server->sendTo($this->clientInfo['address'], $this->clientInfo['port'], $data, $path . (isset($this->requestId) ? self::REQUEST_SEPARATOR . $this->requestId : ''));
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        return $this->clientInfo;
    }
}
