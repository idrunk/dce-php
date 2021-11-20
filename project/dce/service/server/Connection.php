<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/10/18 21:37
 */

namespace dce\service\server;

use dce\loader\attr\Sington;
use dce\project\node\NodeTree;
use dce\project\request\RequestManager;
use dce\project\session\Session;
use Swoole\Http\Request;

class Connection {
    public Session $session;
    public Request $swRequest;
    public NodeTree|null $initialNode;

    private function __construct(
        public int $fd,
        public ServerMatrix $server,
    ) {}

    public function setProps(NodeTree|null $initialNode, Session $session, Request|null $swRequest = null): self {
        $this->initialNode = $initialNode;
        $this->session = $session;
        $swRequest && $this->swRequest = $swRequest;
        return $this;
    }

    public function onRequest(): bool {
        return RequestManager::current()?->fd === $this->fd;
    }

    public function destroy(): void {
        Sington::destroy(self::class, $this->fd);
    }

    public static function from(int $fd, ServerMatrix $server = null): self {
        $instance = Sington::generated(self::class, $fd);
        // 即便前个fd的Connection对象未被清除也没关系，因为后续setProps时会覆盖掉旧的属性
        is_string($instance) && $instance = Sington::logInstance($instance, new self($fd, $server));
        return $instance;
    }

    public static function exists(int $fd): self|null {
        return is_string($instance = Sington::generated(self::class, $fd)) ? null : $instance;
    }
}