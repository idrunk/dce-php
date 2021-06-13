<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/26 16:47
 */

namespace dce\project\session;

use dce\Dce;
use dce\rpc\RpcClient;
use dce\service\server\ConnectionException;
use dce\service\server\ServerMatrix;
use dce\storage\redis\DceRedis;
use rpc\dce\service\RpcServerApi;
use Throwable;

abstract class SessionManager {
    protected static array $config;

    /** 单例 */
    protected function __construct() {}

    /**
     * 实例化一个单例管理器类
     * @return static
     */
    final public static function inst(): static {
        static $instance;
        if (null === $instance) {
            self::initConfig();
            $instance = new self::$config['manager_class']();
        }
        return $instance;
    }

    /**
     * 清空FdForm, 服务器初始化时调用, 防止前次服务器发生异常导致未正常断开连接而留下垃圾数据
     * @param string $apiHost
     * @param int $apiPort
     */
    final public function clear(string $apiHost, int $apiPort): void {
        foreach ($this->listFdForm(0, null, self::genFdid(0, $apiHost, $apiPort) . '/*') as ['fd' => $fdOld, 'host' => $hostOld, 'port' => $portOld]) {
            $this->disconnect($fdOld, $hostOld, $portOld);
        }
    }

    /** 初始化处理Session配置 */
    private static function initConfig(): void {
        if (isset(self::$config)) {
            return;
        }
        self::$config = Dce::$config->session;
        if (! self::$config['manager_class']) {
            self::$config['manager_class'] = DceRedis::isAvailable() ? '\dce\project\session\SessionManagerRedis' : '\dce\project\session\SessionManagerFile';
        }
    }

    /**
     * 连接时同步会话状态
     * @param string $sid
     * @param int $fd
     * @param string $apiHost
     * @param int $apiPort
     * @param string $extra
     */
    public function connect(string $sid, int $fd, string $apiHost, int $apiPort, string $extra): void {
        $fdid = $this->setFdForm($sid, $fd, $apiHost, $apiPort, $extra);
        $this->setSessionForm($sid, $fdid);
        $mid = $this->getSessionForm($sid);
        if ($mid) {
            // 如果sid已经登录了mid, 则将当前fd加入MemberForm
            $this->setMemberForm($mid, $fdid);
        }
    }

    /**
     * 断开连接时同步会话状态
     * @param int $fd
     * @param string $apiHost
     * @param int $apiPort
     */
    public function disconnect(int $fd, string $apiHost, int $apiPort): void {
        $fdid = self::genFdid($fd, $apiHost, $apiPort);
        $fdForm = $this->getFdForm($fdid);
        if ($fdForm) {
            $this->delFdForm($fdid);
            if ($fdForm['sid'] && $mid = $this->getSessionForm($fdForm['sid'])) {
                $this->delSessionForm($fdForm['sid'], $fdid);
                $this->delMemberForm($mid, $fdid);
            }
        }
    }

    /**
     * 处理HTTP登录, 标记相关信息
     * @param int $mid
     * @param string $sid
     */
    public function signIn(int $mid, string $sid): void {
        $fdids = $this->getSessionForm($sid, true);
        if ($fdids) {
            // 如果当前sid有对应长连接, 则标记该连接
            $this->setSessionForm($sid, mid: $mid);
            $this->setMemberForm($mid, $fdids, $sid);
        } else {
            $this->setSessionForm($sid, mid: $mid);
            $this->setMemberForm($mid, sid: $sid);
        }
    }

    /**
     * 处理长连接登录, 标记mid相关信息
     * @param int $mid
     * @param int $fd
     * @param string $apiHost
     * @param int $apiPort
     * @throws SessionException
     */
    public function fdSignIn(int $mid, int $fd, string $apiHost, int $apiPort): void {
        $fdid = self::genFdid($fd, $apiHost, $apiPort);
        if (! $sid = $this->getFdForm($fdid)['sid'] ?? 0) {
            throw (new SessionException(SessionException::SID_BY_FDID_NOTFOUND))->format($fdid);
        }
        $this->setSessionForm($sid, mid: $mid);
        $this->setMemberForm($mid, $fdid, $sid);
    }

    /**
     * 退出登录
     * @param string $sid
     */
    public function signOut(string $sid): void {
        $sessionForm = $this->getSessionForm($sid, null);
        if (! isset($sessionForm['mid'])) {
            return;
        }
        if (isset($sessionForm['fdid'])) {
            $this->delSessionForm($sid, false);
            // 如果有长连接, 则也清除掉
            $this->delMemberForm($sessionForm['mid'], $sessionForm['fdid'], $sid);
        } else {
            // 如果没有长连接, 则直接删掉整个SessionForm
            $this->delSessionForm($sid, null);
            $this->delMemberForm($sessionForm['mid'], sid: $sid);
        }
    }

    /**
     * 取Session实例集, 并删除已失效的sid绑定
     * @param int $mid
     * @return Session[]
     */
    private function getSessionInstances(int $mid): array {
        $sessions = [];
        $sids = $this->getMemberForm($mid, false);
        foreach ($sids as $sid) {
            $session = Session::newBySid($sid);
            if ($session->isAlive()) {
                $sessions[] = $session;
            } else {
                if ($fdids = $this->getSessionForm($sid, true)) {
                    // 在这里仅处理MemberForm与SessionForm而不处理FdForm, 因为删掉FdForm的sid无意义, fd必须绑定sid, 否则无法正常使用, FdForm的维护可以交给Server处理而不在此处
                    $this->delMemberForm($mid, $fdids, $sid);
                } else {
                    $this->delMemberForm($mid, sid: $sid);
                }
                $this->delSessionForm($sid, null);
            }
        }
        return $sessions;
    }

    /**
     * 设置登录用户的全部Session
     * @param int $mid
     * @param string $key
     * @param mixed $value
     */
    public function setSession(int $mid, string $key, mixed $value): void {
        foreach ($this->getSessionInstances($mid) as $session) {
            $session->set($key, $value);
        }
    }

    /**
     * 删除登录用户的全部Session指定键值
     * @param int $mid
     * @param string $key
     */
    public function deleteSession(int $mid, string $key): void {
        foreach ($this->getSessionInstances($mid) as $session) {
            $session->delete($key);
        }
    }

    /**
     * 注销登录用户全部Session
     * @param int $mid
     */
    public function destroySession(int $mid): void {
        foreach ($this->getSessionInstances($mid) as $session) {
            $session->destroy();
        }
    }

    /**
     * 更新Session（将原fd与mid绑定的sid更新为新的）
     * @param Session $session
     * @param bool $longLive
     * @return Session
     */
    public function renewSession(Session $session, bool $longLive = false): Session {
        $originalSid = $session->getId();
        $session->renew($longLive);
        $sessionForm = $this->getSessionForm($originalSid, null);
        if ($sessionForm) {
            if ($sessionForm['mid'] ?? 0) {
                $this->setMemberForm($sessionForm['mid'], sid: $session->getId());
                $this->delMemberForm($sessionForm['mid'], sid: $originalSid);
            }
            $this->setSessionForm($session->getId(), $sessionForm['fdid'] ?? null, $sessionForm['mid'] ?? null);
            $this->delSessionForm($originalSid, null);
        }
        return $session;
    }

    /**
     * 向已连接的指定登录用户发送可跨服务器消息
     * @param int $mid
     * @param mixed $message
     * @param string|false $path
     * @return bool|null {null: 用户未连接, true: 向部分或全部连接发送成功, false: 全部发送失败}
     */
    public function sendMessage(int $mid, mixed $message, string|false $path): bool|null {
        $result = null;
        $fdids = $this->getMemberForm($mid);
        foreach ($fdids as $fdid) {
            try {
                if ($this->sendMessageFd($fdid, $message, $path)) {
                    $result = true;
                } else {
                    // 若fdid无效则删掉登录用户连接标记
                    $this->delMemberForm($mid, $fdid);
                }
            } catch (Throwable $throwable) {
                if ($throwable instanceof ConnectionException) {
                    // 如果是连接错误, 则删掉已登录用户连接标识
                    $this->delMemberForm($mid, $fdid);
                }
                // 发送成功过则不变, 否则设为失败
                $result = $result ?: false;
            }
        }
        return $result;
    }

    /**
     * 向fdid发送消息
     * @param string $fdid
     * @param mixed $message
     * @param string|false $path
     * @return bool {true: 成功, false: fdid无效}
     * @throws ConnectionException 目标fd连接失效抛出该异常
     */
    public function sendMessageFd(string $fdid, mixed $message, string|false $path): bool {
        static $rpcPreloadedMapping = [];
        $fdForm = $this->getFdForm($fdid);
        if ($fdForm['host'] ?? 0) {
            ['sid' => $sid, 'fd' => $fd, 'host' => $host, 'port' => $port, 'extra' => $extra] = $fdForm;
            $hostId = self::genFdid(0, $host, $port);
            if (! key_exists($hostId, $rpcPreloadedMapping)) {
                // 这里多次preload也没关系, 但没必要, 缓存以节省开销
                RpcClient::preload('\rpc\dce\service\RpcServerApi', ['host' => $host, 'port' => $port, 'token' => Dce::$config->serverApiAuthMapping[$hostId] ?? '']);
                $rpcPreloadedMapping[$hostId] = 1;
            }
            try {
                match ($extra) {
                    ServerMatrix::SM_EXTRA_TCP => RpcClient::with($host, $port, fn() => RpcServerApi::send($fd, $message, $path)),
                    ServerMatrix::SM_EXTRA_WS => RpcClient::with($host, $port, fn() => RpcServerApi::push($fd, $message, $path)),
                };
                return true;
            } catch (ConnectionException $exception) {
                // 如果连接异常了, 则删掉关系数据
                $this->delSessionForm($sid, $fdid);
                $this->delFdForm($fdid);
                throw $exception;
            }
        }
        return false;
    }


    /**
     * 生成fdid
     * @param string|int $fd
     * @param string $host
     * @param int $port
     * @return string
     */
    protected static function genFdid(string|int $fd, string $host, int $port): string {
        return is_int($fd) || $host || $port ? "{$host}:{$port}" . ($fd ? "/{$fd}" : '') : $fd;
    }

    /**
     * 添加FdForm映射
     * @param string $sid
     * @param int $fd
     * @param string $host
     * @param int $port
     * @param string $extra
     * @return string
     */
    abstract protected function setFdForm(string $sid, int $fd, string $host, int $port, string $extra): string;

    /**
     * 取FdForm
     * @param string|int $fd
     * @param string $host
     * @param int $port
     * @return array|false
     */
    abstract public function getFdForm(string|int $fd, string $host = '', int $port = 0): array|false;

    /**
     * 删除FdForm
     * @param string|int $fd
     * @param string $host
     * @param int $port
     * @return bool
     */
    abstract protected function delFdForm(string|int $fd, string $host = '', int $port = 0): bool;

    /**
     * 迭代式的取FdForm集 (用于全站群推消息等)
     * @param int $offset
     * @param int|null $limit
     * @param string $pattern
     * @return array
     */
    abstract public function listFdForm(int $offset = 0, int|null $limit = 100, string $pattern = '*'): array;


    /**
     * 设置sid=>SessionForm映射
     * @param string $sid
     * @param string|array|null $fdids
     * @param int|null $mid
     */
    abstract protected function setSessionForm(string $sid, string|array|null $fdids = null, int|null $mid = null): void;

    /**
     * 根据sid取SessionForm
     * @param string $sid
     * @param bool|null $fdidOrMid {null: all, true: fdid, false: mid}
     * @return array|int|false
     */
    abstract public function getSessionForm(string $sid, bool|null $fdidOrMid = false): array|int|false;

    /**
     * 删除sid=>SessionForm映射
     * @param string $sid
     * @param string|array|false|null $fdidOrMid {null: 删SessionForm, string|array: 删fdid, false: 清空mid}
     * @return bool
     */
    abstract protected function delSessionForm(string $sid, string|array|false|null $fdidOrMid): bool;


    /**
     * 绑定mid对应的sid/fdid
     * @param int $mid
     * @param string|array|null $fdids
     * @param string|null $sid
     */
    abstract protected function setMemberForm(int $mid, string|array|null $fdids = null, string|null $sid = null): void;

    /**
     * 取mid对应的sid/fdid集
     * @param int $mid
     * @param bool|null $fdidOrSid {null: all, true: fdidSet, false: sidSet}
     * @return array|false
     */
    abstract public function getMemberForm(int $mid, bool|null $fdidOrSid = true): array|false;

    /**
     * 删除mid对应的sid/fdid (不做清除功能, 因为没有这样的场景, 应该在取数据发现无效时回调删除, 全删完时自动清除)
     * @param int $mid
     * @param string|array|null $fdids
     * @param string|null $sid
     * @return bool
     */
    abstract protected function delMemberForm(int $mid, string|array|null $fdids = null, string|null $sid = null): bool;
}