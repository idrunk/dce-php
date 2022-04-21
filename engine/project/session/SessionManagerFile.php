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

    /** @inheritDoc */
    protected function setFdForm(int $fd, string $host, int $port, string $extra): string {
        $fdid = self::genFdid($fd, $host, $port);
        $mapping = Dce::$cache->file->get(self::FDID_MAPPING_KEY) ?: [];
        $mapping[$fdid] = [
            'fd' => $fd,
            'host' => $host,
            'port' => $port,
            'extra' => $extra,
        ];
        Dce::$cache->file->set(self::FDID_MAPPING_KEY, $mapping);
        return $fdid;
    }

    /** @inheritDoc */
    protected function getFdForm(string|int $fd, string $host = '', int $port = 0): array|false {
        $mapping = Dce::$cache->file->get(self::FDID_MAPPING_KEY);
        return $mapping[self::genFdid($fd, $host, $port)] ?? false;
    }

    /** @inheritDoc */
    protected function delFdForm(string|int $fd, string $host = '', int $port = 0): bool {
        $fdid = self::genFdid($fd, $host, $port);
        $mapping = Dce::$cache->file->get(self::FDID_MAPPING_KEY);
        if (isset($mapping[$fdid])) {
            unset($mapping[$fdid]);
            return Dce::$cache->file->set(self::FDID_MAPPING_KEY, $mapping);
        }
        return true;
    }

    /** @inheritDoc */
    public function listFdForm(int $offset = 0, int|null $limit = 100, string $pattern = '*'): array {
        $queue = Dce::$cache->file->get(self::FDID_MAPPING_KEY) ?: [];
        return array_slice($queue, $offset, $limit, true);
    }

    /** @inheritDoc */
    protected function setSessionForm(string $sid, string|null $fdid = null, int|null $mid = null): void {
        $mapping = Dce::$cache->file->get(self::SID_MAPPING_KEY) ?: [];
        $fdid && $mapping[$sid]['fdid'] = $fdid;
        $mid && $mapping[$sid]['mid'] = $mid;
        Dce::$cache->file->set(self::SID_MAPPING_KEY, $mapping);
    }

    /** @inheritDoc */
    public function getSessionForm(string $sid, bool|null $fdidOrMid = false): array|string|int|false {
        $property = self::sessionBool2Prop($fdidOrMid);
        $mapping = Dce::$cache->file->get(self::SID_MAPPING_KEY);
        return $property ? $mapping[$sid][$property] ?? false : $mapping[$sid] ?? false;
    }

    /** @inheritDoc */
    protected function delSessionForm(string $sid, bool|null $fdidOrMid): bool {
        $mapping = Dce::$cache->file->get(self::SID_MAPPING_KEY);
        if (isset($mapping[$sid])) {
            $prop = self::sessionBool2Prop($fdidOrMid);
            if ($fdidOrMid) unset($mapping[$sid][$prop]);
            if (! $fdidOrMid || ! $mapping[$sid]) unset($mapping[$sid]);
            return Dce::$cache->file->set(self::SID_MAPPING_KEY, $mapping);
        }
        return true;
    }

    /** @inheritDoc */
    protected function setMemberForm(int $mid, string|null $fdid = null, string|null $sid = null): void {
        ! $sid && ! $fdid && throw (new SessionException(SessionException::EMPTY_FORM_PARAMETERS))->format($mid);

        $mapping = Dce::$cache->file->get(self::MID_MAPPING_KEY) ?: [];
        $sid && ! in_array($sid, $mapping[$mid]['sid'] ?? []) && $mapping[$mid]['sid'][] = $sid;
        $fdid && ! in_array($fdid, $mapping[$mid]['fdid'] ?? []) && $mapping[$mid]['fdid'][] = $fdid;

        Dce::$cache->file->set(self::MID_MAPPING_KEY, $mapping);
    }

    /** @inheritDoc */
    public function getMemberForm(int $mid, bool|null $fdidOrSid = true): array|false {
        $property = self::memberBool2Prop($fdidOrSid);
        $mapping = Dce::$cache->file->get(self::MID_MAPPING_KEY);
        return $property ? $mapping[$mid][$property] ?? [] : $mapping[$mid] ?? false;
    }

    /** @inheritDoc */
    protected function delMemberForm(int $mid, string|null $fdid = null, string|null $sid = null): bool {
        $mapping = Dce::$cache->file->get(self::MID_MAPPING_KEY);
        if (isset($mapping[$mid])) {
            $sid && false !== ($index = array_search($sid, $mapping[$mid]['sid'] ?? [])) && array_splice($mapping[$mid]['sid'], $index, 1);
            $fdid && false !== ($index = array_search($fdid, $mapping[$mid]['fdid'] ?? [])) && array_splice($mapping[$mid]['fdid'], $index, 1);
            if (empty($mapping[$mid]['sid']) && empty($mapping[$mid]['fdid'])) unset($mapping[$mid]);

            return Dce::$cache->file->set(self::MID_MAPPING_KEY, $mapping);
        }
        return true;
    }
}
