<?php
/**
 * Author: Drunk
 * Date: 2019-1-21 21:10
 */

namespace dce\project\request;

use dce\base\Exception;
use dce\config\Config;

class SessionCgi extends Session {
    private Config $session;

    /** @inheritDoc */
    public function open(Request $request): void {
        $config = $request->config->session;
        $saveClass = $config['save_class'] ?? null;
        $savePath = $config['save_path'] ?? null;
        if ($saveClass && isset(class_implements($saveClass)['SessionHandlerInterface'])) {
            session_set_save_handler(new $saveClass);
        } else if ($savePath) {
            session_save_path($savePath);
        }
        session_name(self::getSidName());
        session_status() !== PHP_SESSION_ACTIVE ? session_start(): true;
        $this->setId(session_id());
        $this->session = new Config($_SESSION);
    }

    /**
     * 设置session (benchmark 后续做性能测试, 如果$_SESSION=赋值与$_SESSION[$key]=赋值的性能有明显差距, 则改为后者形式)
     * @param string $key
     * @param mixed $value
     * @throws Exception
     */
    public function set(string $key, $value): void {
        $_SESSION = $this->session->set($key, $value)->arrayify();
    }

    /** @inheritDoc */
    public function get(string $key): mixed {
        return $this->session->get($key);
    }

    /** @inheritDoc */
    public function delete($key): void {
        $_SESSION = $this->session->del($key)->arrayify();
    }

    /** @inheritDoc */
    public function getAll(): array {
        return $this->session->arrayify();
    }

    /** @inheritDoc */
    public function destroy(): void {
        $_SESSION = $this->session->empty()->arrayify();
    }
}
