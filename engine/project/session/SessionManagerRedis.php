<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-23 22:12
 */

namespace dce\project\session;

use dce\storage\redis\RedisProxy;

class SessionManagerRedis extends SessionManager {
    private const TTL = 259200; // 秒内未主动删除的将自动过期

    private const FDID_PREFIX = 'sm-fdid:';
    private const SID_PREFIX = 'sm-sid:';
    private const MID_PREFIX = 'sm-mid:';

    private static function fdidKey(string $fdid): string { return self::FDID_PREFIX . $fdid; }
    private static function sidKey(string $sid): string { return self::SID_PREFIX . $sid; }
    private static function midKey(string $mid): string { return self::MID_PREFIX . $mid; }

    /** @inheritDoc */
    protected function setFdForm(int $fd, string $host, int $port, string $extra): string {
        $fdid = self::genFdid($fd, $host, $port);
        $redis = RedisProxy::new(self::$config['manager_index']);
        $redis->hMSet(self::fdidKey($fdid), [
            'fd' => $fd,
            'host' => $host,
            'port' => $port,
            'extra' => $extra,
        ]);
        $redis->expire(self::fdidKey($fdid), self::TTL);
        return $fdid;
    }

    /** @inheritDoc */
    protected function getFdForm(int|string $fd, string $host = '', int $port = 0): array|false {
        $fdid = self::genFdid($fd, $host, $port);
        $redis = RedisProxy::new(self::$config['manager_index']);
        $fdForm = $redis->hGetAll(self::fdidKey($fdid));
        return $fdForm ?: false;
    }

    /** @inheritDoc */
    protected function delFdForm(int|string $fd, string $host = '', int $port = 0): bool {
        $fdid = self::genFdid($fd, $host, $port);
        $redis = RedisProxy::new(self::$config['manager_index']);
        $affected = $redis->del(self::fdidKey($fdid));
        return !! $affected;
    }

    /** @inheritDoc */
    public function listFdForm(int $offset = 0, int|null $limit = 100, string $pattern = '*'): array {
        $redis = RedisProxy::new(self::$config['manager_index']);
        $limit ??= 65535;
        $upperLimit = $offset + $limit;
        $index = 0;
        $list = [];
        $prefixLength = strlen(self::FDID_PREFIX);
        // 迭代式取FdForm集
        while($index < $upperLimit && $keys = $redis->scan($iterator, self::fdidKey($pattern), $limit)) {
            $count = count($keys);
            $diff = $offset - $index;
            if ($diff <= $count) {
                $entered = $index >= $offset;
                $overflowing = $index + $count > $upperLimit;
                $sliceOffset = $entered ? 0 : $diff;
                $sliceLength = $entered && $overflowing ? $upperLimit - $index : ($count > $limit ? $limit : $count);
                // 仅取有效范围中的FdForm
                foreach (array_slice($keys, $sliceOffset, $sliceLength) as $key) {
                    $list[substr($key, $prefixLength)] = $redis->hGetAll($key);
                }
            }
            $index += $count;
        }
        return $list;
    }

    /** @inheritDoc */
    protected function setSessionForm(string $sid , string|null $fdid = null , int|null $mid = null): void {
        $redis = RedisProxy::new(self::$config['manager_index']);
        $form = $redis->hGetAll(self::sidKey($sid)) ?: [];
        $fdid && $form['fdid'] = $fdid;
        $mid && $form['mid'] = $mid;
        $redis->hMSet(self::sidKey($sid), $form);
        $redis->expire(self::sidKey($sid), self::TTL);
    }

    /** @inheritDoc */
    public function getSessionForm(string $sid , bool|null $fdidOrMid = false): array|string|int|false {
        $redis = RedisProxy::new(self::$config['manager_index']);
        $data = $redis->hGetAll(self::sidKey($sid));
        $property = self::sessionBool2Prop($fdidOrMid);
        return $property ? $data[$property] ?? false : $data ?? false;
    }

    /** @inheritDoc */
    protected function delSessionForm(string $sid , bool|null $fdidOrMid): bool {
        $result = true;
        $redis = RedisProxy::new(self::$config['manager_index']);
        if ($form = $redis->hGetAll(self::sidKey($sid))) {
            $prop = self::sessionBool2Prop($fdidOrMid);
            if ($prop) {
                $result = $redis->hDel(self::sidKey($sid), $prop);
                unset($form[$prop]);
            }
            ! ($prop && $form) && $result = $redis->del(self::sidKey($sid));
        }
        return $result;
    }

    /** @inheritDoc */
    protected function setMemberForm(int $mid , string|null $fdid = null , string|null $sid = null): void {
        ! $sid && ! $fdid && throw (new SessionException(SessionException::EMPTY_FORM_PARAMETERS))->format($mid);

        $redis = RedisProxy::new(self::$config['manager_index']);
        $form = $redis->hGetAll(self::midKey($mid));

        $sid && ! in_array($sid, $form['sid'] ?? []) && $form['sid'][] = $sid;
        $fdid && ! in_array($fdid, $form['fdid'] ?? []) && $form['fdid'][] = $fdid;

        $redis->hMSet(self::midKey($mid), $form);
        $redis->expire(self::midKey($mid), self::TTL);
    }

    /** @inheritDoc */
    public function getMemberForm(int $mid , bool|null $fdidOrSid = true): array|false {
        $redis = RedisProxy::new(self::$config['manager_index']);
        $form = $redis->hGetAll(self::midKey($mid));
        $property = self::memberBool2Prop($fdidOrSid);
        return $property ? $form[$property] ?? [] : $form ?? false;
    }

    /** @inheritDoc */
    protected function delMemberForm(int $mid , string|null $fdid = null , string|null $sid = null): bool {
        $result = true;
        $redis = RedisProxy::new(self::$config['manager_index']);
        if ($form = $redis->hGetAll(self::midKey($mid))) {
            $sid && false !== ($index = array_search($sid, $form['sid'] ?? [])) && array_splice($form['sid'], $index, 1);
            $fdid && false !== ($index = array_search($fdid, $form['fdid'] ?? [])) && array_splice($form['fdid'], $index, 1);
            $result = empty($form['sid']) && empty($form['fdid']) ? $redis->del(self::midKey($mid)) : $redis->hMSet(self::midKey($mid), $form);
        }
        return $result;
    }
}