<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/26 1:41
 */

namespace dce\project\session;

use dce\Dce;

class SessionManagerFile extends SessionManager {
    private const FDID_MAPPING_KEY = 'session-manager-fdid';

    private const SID_MAPPING_KEY = 'session-manager-sid';

    private const MID_MAPPING_KEY = 'session-manager-mid';

    /**
     * @inheritDoc
     */
    protected function setFdForm(string $sid, int $fd, string $host, int $port, string $extra): string {
        $fdid = self::genFdid($fd, $host, $port);
        $mapping = Dce::$cache->file->get(self::FDID_MAPPING_KEY) ?: [];
        $mapping[$fdid] = [
            'sid' => $sid,
            'fd' => $fd,
            'host' => $host,
            'port' => $port,
            'extra' => $extra,
        ];
        Dce::$cache->file->set(self::FDID_MAPPING_KEY, $mapping);
        return $fdid;
    }

    /**
     * @inheritDoc
     */
    public function getFdForm(string|int $fd, string $host = '', int $port = 0): array|false {
        $mapping = Dce::$cache->file->get(self::FDID_MAPPING_KEY);
        return $mapping[self::genFdid($fd, $host, $port)] ?? false;
    }

    /**
     * @inheritDoc
     */
    protected function delFdForm(string|int $fd, string $host = '', int $port = 0): bool {
        $fdid = self::genFdid($fd, $host, $port);
        $mapping = Dce::$cache->file->get(self::FDID_MAPPING_KEY);
        if (isset($mapping[$fdid])) {
            unset($mapping[$fdid]);
            return Dce::$cache->file->set(self::FDID_MAPPING_KEY, $mapping);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function listFdForm(int $offset = 0, int|null $limit = 100, string $pattern = '*'): array {
        $queue = Dce::$cache->file->get(self::FDID_MAPPING_KEY) ?: [];
        return array_slice($queue, $offset, $limit, true);
    }

    /**
     * @inheritDoc
     */
    protected function setSessionForm(string $sid, string|array|null $fdids = null, int|null $mid = null): void {
        $mapping = Dce::$cache->file->get(self::SID_MAPPING_KEY) ?: [];
        foreach (is_array($fdids) ? $fdids : [$fdids] as $fdid) {
            if ($fdid && ! in_array($fdid, $mapping[$sid]['fdid'] ?? [])) {
                $mapping[$sid]['fdid'][] = $fdid;
            }
        }
        if ($mid) {
            $mapping[$sid]['mid'] = $mid;
        }
        Dce::$cache->file->set(self::SID_MAPPING_KEY, $mapping);
    }

    /**
     * @inheritDoc
     */
    public function getSessionForm(string $sid, bool|null $fdidOrMid = false): array|int|false {
        $property = $fdidOrMid ? 'fdid' : (false === $fdidOrMid ? 'mid' : null);
        $mapping = Dce::$cache->file->get(self::SID_MAPPING_KEY);
        return $property ? $mapping[$sid][$property] ?? false : $mapping[$sid] ?? false;
    }

    /**
     * @inheritDoc
     */
    protected function delSessionForm(string $sid, string|array|false|null $fdidOrMid): bool {
        $mapping = Dce::$cache->file->get(self::SID_MAPPING_KEY);
        if (isset($mapping[$sid])) {
            if ($fdidOrMid) {
                foreach (is_array($fdidOrMid) ? $fdidOrMid : [$fdidOrMid] as $fdid) {
                    if ($fdid && false !== ($index = array_search($fdid, $mapping[$sid]['fdid'] ?? []))) {
                        array_splice($mapping[$sid]['fdid'], $index, 1);
                        if (! $mapping[$sid]['fdid']) {
                            unset($mapping[$sid]['fdid']);
                        }
                    }
                }
            } else if (false === $fdidOrMid) {
                unset($mapping[$sid]['mid']);
            }
            if (null === $fdidOrMid || ! $mapping[$sid]) {
                // 如果需要删SessionForm或者已经空了, 则整个删掉
                unset($mapping[$sid]);
            }
            return Dce::$cache->file->set(self::SID_MAPPING_KEY, $mapping);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function setMemberForm(int $mid, string|array|null $fdids = null, string|null $sid = null): void {
        if (! $sid && ! $fdids) {
            throw (new SessionException(SessionException::EMPTY_FORM_PARAMETERS))->format($mid);
        }
        $mapping = Dce::$cache->file->get(self::MID_MAPPING_KEY) ?: [];
        if ($sid && ! in_array($sid, $mapping[$mid]['sid'] ?? [])) {
            $mapping[$mid]['sid'][] = $sid;
        }
        foreach (is_array($fdids) ? $fdids : [$fdids] as $fdid) {
            if ($fdid && ! in_array($fdid, $mapping[$mid]['fdid'] ?? [])) {
                $mapping[$mid]['fdid'][] = $fdid;
            }
        }
        Dce::$cache->file->set(self::MID_MAPPING_KEY, $mapping);
    }

    /**
     * @inheritDoc
     */
    public function getMemberForm(int $mid, bool|null $fdidOrSid = true): array|false {
        $property = $fdidOrSid ? 'fdid' : (false === $fdidOrSid ? 'sid' : null);
        $mapping = Dce::$cache->file->get(self::MID_MAPPING_KEY);
        return $property ? $mapping[$mid][$property] ?? [] : $mapping[$mid] ?? false;
    }

    /**
     * @inheritDoc
     */
    protected function delMemberForm(int $mid, string|array|null $fdids = null, string|null $sid = null): bool {
        $mapping = Dce::$cache->file->get(self::MID_MAPPING_KEY);
        if (isset($mapping[$mid])) {
            if ($sid && false !== ($index = array_search($sid, $mapping[$mid]['sid'] ?? []))) {
                array_splice($mapping[$mid]['sid'], $index, 1);
            }
            foreach (is_array($fdids) ? $fdids : [$fdids] as $fdid) {
                if ($fdid && false !== ($index = array_search($fdid, $mapping[$mid]['fdid'] ?? []))) {
                    array_splice($mapping[$mid]['fdid'], $index, 1);
                }
            }
            if (empty($mapping[$mid]['sid']) && empty($mapping[$mid]['fdid'])) {
                unset($mapping[$mid]);
            }
            return Dce::$cache->file->set(self::MID_MAPPING_KEY, $mapping);
        }
        return true;
    }
}
