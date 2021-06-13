<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-23 22:12
 */

namespace dce\project\session;

use dce\storage\redis\DceRedis;

class SessionManagerRedis extends SessionManager {
    private const TTL = 259200; // 秒内未主动删除的将自动过期

    private const FDID_PREFIX = 'sm-fdid:';

    private const SID_PREFIX = 'sm-sid:';

    private const MID_PREFIX = 'sm-mid:';

    /** @inheritDoc */
    protected function setFdForm(string $sid, int $fd, string $host, int $port, string $extra): string {
        $fdid = self::genFdid($fd, $host, $port);
        $redis = DceRedis::get(self::$config['manager_index']);
        $redis->hMSet(self::FDID_PREFIX . $fdid, [
            'sid' => $sid,
            'fd' => $fd,
            'host' => $host,
            'port' => $port,
            'extra' => $extra,
        ]);
        $redis->expire(self::FDID_PREFIX . $fdid, self::TTL);
        DceRedis::put($redis);
        return $fdid;
    }

    /** @inheritDoc */
    public function getFdForm(int|string $fd, string $host = '', int $port = 0): array|false {
        $fdid = self::genFdid($fd, $host, $port);
        $redis = DceRedis::get(self::$config['manager_index']);
        $fdForm = $redis->hGetAll(self::FDID_PREFIX . $fdid);
        DceRedis::put($redis);
        return $fdForm ?: false;
    }

    /** @inheritDoc */
    protected function delFdForm(int|string $fd, string $host = '', int $port = 0): bool {
        $fdid = self::genFdid($fd, $host, $port);
        $redis = DceRedis::get(self::$config['manager_index']);
        $affected = $redis->del(self::FDID_PREFIX . $fdid);
        DceRedis::put($redis);
        return !! $affected;
    }

    /** @inheritDoc */
    public function listFdForm(int $offset = 0, int|null $limit = 100, string $pattern = '*'): array {
        $redis = DceRedis::get(self::$config['manager_index']);
        $limit ??= 65535;
        $upperLimit = $offset + $limit;
        $index = 0;
        $list = [];
        $prefixLength = strlen(self::FDID_PREFIX);
        // 迭代式取FdForm集
        while($index < $upperLimit && $keys = $redis->scan($iterator, self::FDID_PREFIX . $pattern, $limit)) {
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
        DceRedis::put($redis);
        return $list;
    }

    /** @inheritDoc */
    protected function setSessionForm(string $sid , string|array|null $fdids = null , int|null $mid = null): void {
        $redis = DceRedis::get(self::$config['manager_index']);
        $form = $redis->hGetAll(self::SID_PREFIX . $sid) ?: [];
        foreach (is_array($fdids) ? $fdids : [$fdids] as $fdid) {
            if ($fdid && ! in_array($fdid, $form['fdid'] ?? [])) {
                $form['fdid'][] = $fdid;
            }
        }
        if ($mid) {
            $form['mid'] = $mid;
        }
        $redis->hMSet(self::SID_PREFIX . $sid, $form);
        $redis->expire(self::SID_PREFIX . $sid, self::TTL);
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function getSessionForm(string $sid , bool|null $fdidOrMid = false): array|int|false {
        $redis = DceRedis::get(self::$config['manager_index']);
        $data = $redis->hGetAll(self::SID_PREFIX . $sid);
        DceRedis::put($redis);
        $property = $fdidOrMid ? 'fdid' : (false === $fdidOrMid ? 'mid' : null);
        return $property ? $data[$property] ?? false : $data ?? false;
    }

    /** @inheritDoc */
    protected function delSessionForm(string $sid , string|array|false|null $fdidOrMid): bool {
        $result = true;
        $redis = DceRedis::get(self::$config['manager_index']);
        if ($form = $redis->hGetAll(self::SID_PREFIX . $sid)) {
            if ($fdidOrMid) {
                foreach (is_array($fdidOrMid) ? $fdidOrMid : [$fdidOrMid] as $fdid) {
                    if ($fdid && false !== ($index = array_search($fdid, $form['fdid'] ?? []))) {
                        array_splice($form['fdid'], $index, 1);
                    }
                }
                if (! $form['fdid']) {
                    // 如果集删完了, 则整个直接干掉
                    $result = $redis->hDel(self::SID_PREFIX . $sid, 'fdid');
                } else {
                    $result = $redis->hSet(self::SID_PREFIX . $sid, 'fdid', $form['fdid']);
                }
            } else if (false === $fdidOrMid) {
                $result = $redis->hDel(self::SID_PREFIX . $sid, 'mid');
            } else {
                $result = $redis->del(self::SID_PREFIX . $sid);
            }
        }
        DceRedis::put($redis);
        return $result;
    }

    /** @inheritDoc */
    protected function setMemberForm(int $mid , string|array|null $fdids = null , string|null $sid = null): void {
        if (! $sid && ! $fdids) {
            throw (new SessionException(SessionException::EMPTY_FORM_PARAMETERS))->format($mid);
        }
        $redis = DceRedis::get(self::$config['manager_index']);
        $form = $redis->hGetAll(self::MID_PREFIX . $mid);
        if ($sid && ! in_array($sid, $form['sid'] ?? [])) {
            $form['sid'][] = $sid;
        }
        foreach (is_array($fdids) ? $fdids : [$fdids] as $fdid) {
            if ($fdid && ! in_array($fdid, $form['fdid'] ?? [])) {
                $form['fdid'][] = $fdid;
            }
        }
        $redis->hMSet(self::MID_PREFIX . $mid, $form);
        $redis->expire(self::MID_PREFIX . $mid, self::TTL);
        DceRedis::put($redis);
    }

    /** @inheritDoc */
    public function getMemberForm(int $mid , bool|null $fdidOrSid = true): array|false {
        $redis = DceRedis::get(self::$config['manager_index']);
        $form = $redis->hGetAll(self::MID_PREFIX . $mid);
        DceRedis::put($redis);
        $property = $fdidOrSid ? 'fdid' : (false === $fdidOrSid ? 'sid' : null);
        return $property ? $form[$property] ?? [] : $form ?? false;
    }

    /** @inheritDoc */
    protected function delMemberForm(int $mid , string|array|null $fdids = null , string|null $sid = null): bool {
        $result = true;
        $redis = DceRedis::get(self::$config['manager_index']);
        if ($form = $redis->hGetAll(self::MID_PREFIX . $mid)) {
            if ($sid && false !== ($index = array_search($sid, $form['sid'] ?? []))) {
                array_splice($form['sid'], $index, 1);
            }
            foreach (is_array($fdids) ? $fdids : [$fdids] as $fdid) {
                if ($fdid && false !== ($index = array_search($fdid, $form['fdid'] ?? []))) {
                    array_splice($form['fdid'], $index, 1);
                }
            }
            if (empty($form['sid']) && empty($form['fdid'])) {
                $result = $redis->del(self::MID_PREFIX . $mid);
            } else {
                $result = $redis->hMSet(self::MID_PREFIX . $mid, $form);
            }
        }
        DceRedis::put($redis);
        return $result;
    }
}