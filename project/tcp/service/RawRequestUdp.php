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
        private string $packet,
        private array $clientInfo,
    ) {
        $this->clientInfo['ip'] = $this->clientInfo['address'];
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
        ['path' => $path, 'data' => $rawData, 'dataParsed' => $dataParsed] = $this->unPack($this->packet);
        $this->path = $path;
        $this->rawData = $rawData;
        $this->dataParsed = $dataParsed;
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
        return $this->server->sendTo($this->clientInfo['address'], $this->clientInfo['port'], $data, $path);
    }

    /** @inheritDoc */
    public function getClientInfo(): array {
        return $this->clientInfo;
    }
}
