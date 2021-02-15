<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/26 16:47
 */

namespace dce\project\request;

abstract class SessionManager {
    /**
     * 记录sid与fd映射, 若通过sid找到了mid, 则同时记录mid
     * @param string $sid
     * @param int $fd
     * @param string $apiHost
     * @param int $apiPort
     * @param string $extra
     */
    public function logFdBySid(string $sid, int $fd, string $apiHost, int $apiPort, string $extra = ''): void {
        $mid = $sid ? ($this->filterBySid($sid)[0]['mid'] ?? 0): 0;
        $this->logFd($sid, $mid, $fd, $apiHost, $apiPort, $extra);
    }

    /**
     * 根据fd删除SessionForm记录
     * @param int $fd
     * @param string $apiHost
     * @param int $apiPort
     * @param string $extra
     */
    public function unLogByFd(int $fd, string $apiHost, int $apiPort, string $extra = ''): void {
        $ids = array_column($this->filterByFd($fd, $apiHost, $apiPort, $extra), 'id');
        foreach ($ids as $id) {
            $this->unLog($id);
        }
    }

    /**
     * 尝试删除删除SessionForm记录
     * @param int $id
     */
    public function tryUnLog(int $id): void {
        if ($id) {
            $this->unLog($id);
        }
    }

    /**
     * 记录长连接Session映射
     * @param string $sid
     * @param int $mid
     * @param int $fd
     * @param string $apiHost
     * @param int $apiPort
     * @param string $extra
     */
    public function logFd(string $sid, int $mid, int $fd, string $apiHost, int $apiPort, string $extra = ''): void {
        if ($fd) {
            $needAdd = true;
            $logs = $this->filterByFd($fd, $apiHost, $apiPort);
            foreach ($logs as $log) {
                $this->updateLog($log->id, [
                    'mid' => $mid,
                    'sid' => $sid,
                ]);
                if ($extra === $log->extra) {
                    // 如果有完全匹配的fd, 则表示更新, 无需添加
                    $needAdd = false;
                }
            }
            if (! $needAdd) {
                return;
            }
        } else {
            // 如果未传入fd, 则表示是HttpSession或者更新长连接Session的mid
            $logs = $this->filterBySid($sid);
            if ($logs) {
                // 如果根据sid查到了记录, 则表示为更新mid, 更新完直接返回就完事了
                foreach ($logs as $log) {
                    $this->updateLog($log->id, [
                        'mid' => $mid,
                    ]);
                }
                return;
            }
        }
        $this->logFdMid($sid, $mid, $fd, $apiHost, $apiPort, $extra);
    }

    /**
     * 记录Session映射
     * @param string $sid
     * @param int $mid
     */
    public function logMid(string $sid, int $mid): void {
        if ($sid) {
            $this->logFd($sid, $mid, 0, '', 0, '');
        }
    }

    /**
     * 记录长连接Session映射
     * @param string $sid
     * @param int $mid
     * @param int $fd
     * @param string $apiHost
     * @param int $apiPort
     * @param string $extra
     */
    abstract protected function logFdMid(string $sid, int $mid, int $fd, string $apiHost, int $apiPort, string $extra = ''): void;

    /**
     * 更新Session映射记录
     * @param int $id
     * @param array $data
     */
    abstract protected function updateLog(int $id, array $data): void;

    /**
     * 删除SessionForm记录
     * @param int $id
     */
    abstract public function unLog(int $id): void;

    /**
     * 按mid筛选SessionForm
     * @param int $mid
     * @return SessionForm[]
     */
    abstract public function filterByMid(int $mid): array;

    /**
     * 按sid筛选SessionForm
     * @param string $sid
     * @return  SessionForm[]
     */
    abstract public function filterBySid(string $sid): array;

    /**
     * 按fd筛选SessionForm
     * @param int $fd
     * @param string $apiHost
     * @param int $apiPort
     * @param string $extra
     * @return  SessionForm[]
     */
    abstract public function filterByFd(int $fd, string $apiHost, int $apiPort, string $extra = ''): array;

    /**
     * 批量更新某mid的Session
     * @param int $mid
     * @param string $key
     * @param mixed $value
     */
    abstract public function updateSession(int $mid, string $key, mixed $value): void;

    /**
     * 批量销毁某mid的Session
     * @param int $mid
     */
    abstract public function destroySession(int $mid): void;

    /**
     * 过期映射回收器
     */
    abstract public function expiredCollection(): void;
}