<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-12-16 20:59
 */

namespace dce\storage\redis;

use dce\base\SwooleUtility;
use dce\Dce;
use Redis;

/**
 * @note 从IDE助手文件中提取未deprecated的方法 (?=(?:#\[(?!Deprecated).+\]|\*\/)\s+)[^\n]+\s+(public function\s+[a-z\d]+\([^)]*\))[^{]+
 * @note 提取未deprecated的参数与返回值类型 (?:@param\s+[^$]+?\$\w+|@return\s+(?:\w+\|?)+)(?=(?:(?!public function|#\[Deprecated[^]]+\]\s+).)+public function \w+)
 */
abstract class RedisProxy {
    protected Redis $redis;

    protected function __construct(
        private int $index,
        private bool $noSerialize,
    ) {
        if ($index > -1 && $index != Dce::$config->redis['index']) $this->redis->select($index);
        $noSerialize && $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
    }

    public function __destruct() {
        // 如果修改了默认库, 则还原选库
        Dce::$config->redis['index'] !== $this->index && $this->redis->select(Dce::$config->redis['index']);
        // 如果设置为了不编码, 则重置为自动编码
        $this->noSerialize && $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
        unset($this->redis);
    }

    public static function new(int $index = -1, bool $noSerialize = false): static {
        static $class;
        $class ??= SwooleUtility::inSwoole() ? RedisProxyPool::class : RedisProxySimple::class;
        return new $class($index, $noSerialize);
    }

    /**
     * 判断Redis是否可用
     * @return bool
     */
    public static function isAvailable(): bool {
        return isset(Dce::$config->redis['host']);
    }

    public function swapdb(int $db1, int $db2): bool {
        return $this->redis->swapdb($db1, $db2);
    }

    public function setOption(int $option, mixed $value): bool {
        return $this->redis->setOption($option, $value);
    }

    public function getOption(int $option): mixed {
        return $this->redis->getOption($option);
    }

    public function ping(string $message = null): bool|string {
        return $this->redis->ping($message);
    }

    public function echo(string $message): string {
        return $this->redis->echo($message);
    }

    public function get(string $key): mixed {
        return $this->redis->get($key);
    }

    public function set(string $key, mixed $value, int|array $timeout = null): bool {
        return $this->redis->set($key, $value, $timeout);
    }

    public function setex(string $key, int $ttl, mixed $value): bool {
        return $this->redis->setex($key, $ttl, $value);
    }

    public function psetex(string $key, int $ttl, mixed $value): bool {
        return $this->redis->psetex($key, $ttl, $value);
    }

    public function setnx(string $key, mixed $value): bool {
        return $this->redis->setnx($key, $value);
    }

    public function del(int|string|array $key1, int|string ...$otherKeys): int {
        return $this->redis->del($key1, ...$otherKeys);
    }

    public function unlink(string|array $key1, string $key2 = null, string $key3 = null): int {
        return $this->redis->unlink($key1, $key2, $key3);
    }

    public function multi(int $mode = Redis::MULTI): Redis {
        return $this->redis->multi($mode);
    }

    public function pipeline(): Redis {
        return $this->redis->pipeline();
    }

    public function exec(): array|null {
        return $this->redis->exec();
    }

    public function discard(): void {
        $this->redis->discard();
    }

    public function watch(string|array $key): void {
        $this->redis->watch($key);
    }

    public function unwatch(): void {
        $this->redis->unwatch();
    }

    public function subscribe(array $channels, string|array $callback): mixed {
        return $this->redis->subscribe($channels, $callback);
    }

    public function psubscribe(array $patterns, string|array $callback): mixed {
        return $this->redis->psubscribe($patterns, $callback);
    }

    public function publish(string $channel, string $message): int {
        return $this->redis->publish($channel, $message);
    }

    public function pubsub(string $keyword, string|array $argument): array|int {
        return $this->redis->pubsub($keyword, $argument);
    }

    public function unsubscribe(array $channels = null): void {
        $this->redis->unsubscribe($channels);
    }

    public function punsubscribe(array $patterns = null): void {
        $this->redis->punsubscribe($patterns);
    }

    public function exists(string|array $key): int|bool {
        return $this->redis->exists($key);
    }

    public function incr(string $key): int {
        return $this->redis->incr($key);
    }

    public function incrByFloat(string $key, float $increment): float {
        return $this->redis->incrByFloat($key, $increment);
    }

    public function incrBy(string $key, int $value): int {
        return $this->redis->incrBy($key, $value);
    }

    public function decr(string $key): bool {
        return $this->redis->decr($key);
    }

    public function decrBy(string $key, int $value): int {
        return $this->redis->decrBy($key, $value);
    }

    public function lPush(string $key, mixed ...$value1): int|false {
        return $this->redis->lPush($key, ...$value1);
    }

    public function rPush(string $key, mixed ...$value1): int|false {
        return $this->redis->rPush($key, ...$value1);
    }

    public function lPushx(string $key, mixed $value): int|false {
        return $this->redis->lPushx($key, $value);
    }

    public function rPushx(string $key, mixed $value): int|false {
        return $this->redis->rPushx($key, $value);
    }

    public function lPop(string $key): mixed {
        return $this->redis->lPop($key);
    }

    public function rPop(string $key): mixed {
        return $this->redis->rPop($key);
    }

    public function blPop(string|array $keys, int $timeout): array {
        return $this->redis->blPop($keys, $timeout);
    }

    public function brPop(string|array $keys, int $timeout): array {
        return $this->redis->brPop($keys, $timeout);
    }

    public function lLen(string $key): int|bool {
        return $this->redis->lLen($key);
    }

    public function lIndex(string $key, int $index): mixed {
        return $this->redis->lIndex($key, $index);
    }

    public function lSet(string $key, int $index, string $value): bool {
        return $this->redis->lSet($key, $index, $value);
    }

    public function lRange(string $key, int $start, int $end): array {
        return $this->redis->lRange($key, $start, $end);
    }

    public function lTrim(string $key, int $start, int $stop): array|false {
        return $this->redis->lTrim($key, $start, $stop);
    }

    public function lRem(string $key, string $value, int $count): int|bool {
        return $this->redis->lRem($key, $value, $count);
    }

    public function lInsert(string $key, int $position, string $pivot, mixed $value): int {
        return $this->redis->lInsert($key, $position, $pivot, $value);
    }

    public function sAdd(string $key, mixed ...$value1): int|bool {
        return $this->redis->sAdd($key, ...$value1);
    }

    public function sRem(string $key, mixed ...$member1): int {
        return $this->redis->sRem($key, ...$member1);
    }

    public function sMove(string $srcKey, string $dstKey, mixed $member): bool {
        return $this->redis->sMove($srcKey, $dstKey, $member);
    }

    public function sIsMember(string $key, mixed $value): bool {
        return $this->redis->sIsMember($key, $value);
    }

    public function sCard(string $key): int {
        return $this->redis->sCard($key);
    }

    public function sPop(string $key, int $count = 1): mixed {
        return $this->redis->sPop($key, $count);
    }

    public function sRandMember(string $key, int $count = 1): mixed {
        return $this->redis->sRandMember($key, $count);
    }

    public function sInter(string $key1, string ...$otherKeys): array|false {
        return $this->redis->sInter($key1, ...$otherKeys);
    }

    public function sInterStore(string $dstKey, string $key1, string ...$otherKeys): int|false {
        return $this->redis->sInterStore($dstKey, $key1, ...$otherKeys);
    }

    public function sUnion(string $key1, string ...$otherKeys): array {
        return $this->redis->sUnion($key1, ...$otherKeys);
    }

    public function sUnionStore(string $dstKey, string $key1, string ...$otherKeys): int {
        return $this->redis->sUnionStore($dstKey, $key1, ...$otherKeys);
    }

    public function sDiff(string $key1, string ...$otherKeys): array {
        return $this->redis->sDiff($key1, ...$otherKeys);
    }

    public function sDiffStore(string $dstKey, string $key1, string ...$otherKeys): int|false {
        return $this->redis->sDiffStore($dstKey, $key1, ...$otherKeys);
    }

    public function sMembers(string $key): array {
        return $this->redis->sMembers($key);
    }

    public function sScan(string $key, int &$iterator, string $pattern = null, int $count = 0): array|false {
        return $this->redis->sScan($key, $iterator, $pattern, $count);
    }

    public function getSet(string $key, mixed $value): mixed {
        return $this->redis->getSet($key, $value);
    }

    public function randomKey(): string {
        return $this->redis->randomKey();
    }

    public function select(int $dbIndex): bool {
        return $this->redis->select($dbIndex);
    }

    public function move(string $key, int $dbIndex): bool {
        return $this->redis->move($key, $dbIndex);
    }

    public function rename(string $srcKey, string $dstKey): bool {
        return $this->redis->rename($srcKey, $dstKey);
    }

    public function renameNx(string $srcKey, string $dstKey): bool {
        return $this->redis->renameNx($srcKey, $dstKey);
    }

    public function expire(string $key, int $ttl): bool {
        return $this->redis->expire($key, $ttl);
    }

    public function pExpire(string $key, int $ttl): bool {
        return $this->redis->pExpire($key, $ttl);
    }

    public function expireAt(string $key, int $timestamp): bool {
        return $this->redis->expireAt($key, $timestamp);
    }

    public function pExpireAt(string $key, int $timestamp): bool {
        return $this->redis->pExpireAt($key, $timestamp);
    }

    public function keys(string $pattern): array {
        return $this->redis->keys($pattern);
    }

    public function dbSize(): int {
        return $this->redis->dbSize();
    }

    public function auth(string|array $password): bool {
        return $this->redis->auth($password);
    }

    public function bgrewriteaof(): bool {
        return $this->redis->bgrewriteaof();
    }

    public function slaveof(string $host = '127.0.0.1', int $port = 6379): bool {
        return $this->redis->slaveof($host, $port);
    }

    public function slowLog(string $operation, int $length = null): mixed {
        return $this->redis->slowLog($operation, $length);
    }

    public function object(string $string = '', string $key = ''): string|int|false {
        return $this->redis->object($string, $key);
    }

    public function save(): bool {
        return $this->redis->save();
    }

    public function bgsave(): bool {
        return $this->redis->bgsave();
    }

    public function lastSave(): int {
        return $this->redis->lastSave();
    }

    public function wait(int $numSlaves, int $timeout): int {
        return $this->redis->wait($numSlaves, $timeout);
    }

    public function type(string $key): int {
        return $this->redis->type($key);
    }

    public function append(string $key, mixed $value): int {
        return $this->redis->append($key, $value);
    }

    public function getRange(string $key, int $start, int $end): string {
        return $this->redis->getRange($key, $start, $end);
    }

    public function setRange(string $key, int $offset, string $value): int {
        return $this->redis->setRange($key, $offset, $value);
    }

    public function strlen(string $key): int {
        return $this->redis->strlen($key);
    }

    public function bitpos(string $key, int $bit, int $start = 0, int $end = null): int {
        return $this->redis->bitpos($key, $bit, $start, $end);
    }

    public function getBit(string $key, int $offset): int {
        return $this->redis->getBit($key, $offset);
    }

    public function setBit(string $key, int $offset, bool|int $value): int {
        return $this->redis->setBit($key, $offset, $value);
    }

    public function bitCount(string $key): int {
        return $this->redis->bitCount($key);
    }

    public function bitOp(string $operation, string $retKey, string $key1, string ...$otherKeys): int {
        return $this->redis->bitOp($operation, $retKey, $key1, ...$otherKeys);
    }

    public function flushDB(): bool {
        return $this->redis->flushDB();
    }

    public function flushAll(): bool {
        return $this->redis->flushAll();
    }

    public function sort(string $key, array $option = null): array {
        return $this->redis->sort($key, $option);
    }

    public function info(string $option = null): array {
        return $this->redis->info($option);
    }

    public function resetStat(): bool {
        return $this->redis->resetStat();
    }

    public function ttl(string $key): int|bool {
        return $this->redis->ttl($key);
    }

    public function pttl(string $key): int|bool {
        return $this->redis->pttl($key);
    }

    public function persist(string $key): bool {
        return $this->redis->persist($key);
    }

    public function mset(array $array): bool {
        return $this->redis->mset($array);
    }

    public function mget(array $array): array {
        return $this->redis->mget($array);
    }

    public function msetnx(array $array): int {
        return $this->redis->msetnx($array);
    }

    public function rpoplpush(string $srcKey, string $dstKey): mixed {
        return $this->redis->rpoplpush($srcKey, $dstKey);
    }

    public function brpoplpush(string $srcKey, string $dstKey, int $timeout): mixed {
        return $this->redis->brpoplpush($srcKey, $dstKey, $timeout);
    }

    public function zAdd(string $key, array|float $options, mixed $score1, mixed $value1 = null, mixed $score2 = null, mixed $value2 = null, mixed $scoreN = null, mixed $valueN = null): int {
        return $this->redis->zAdd($key, $options, $score1, $value1, $score2, $value2, $scoreN, $valueN);
    }

    public function zRange(string $key, int $start, int $end, bool $withscores = null): array {
        return $this->redis->zRange($key, $start, $end, $withscores);
    }

    public function zRem(string $key, mixed $member1, mixed ...$otherMembers): int {
        return $this->redis->zRem($key, $member1, ...$otherMembers);
    }

    public function zRevRange(string $key, int $start, int $end, bool $withscore = null): array {
        return $this->redis->zRevRange($key, $start, $end, $withscore);
    }

    public function zRangeByScore(string $key, int $start, int $end, array $options = []): array {
        return $this->redis->zRangeByScore($key, $start, $end, $options);
    }

    public function zRevRangeByScore(string $key, int $start, int $end, array $options = []): array {
        return $this->redis->zRevRangeByScore($key, $start, $end, $options);
    }

    public function zRangeByLex(string $key, int $min, int $max, int $offset = null, int $limit = null): array|false {
        return $this->redis->zRangeByLex($key, $min, $max, $offset, $limit);
    }

    public function zRevRangeByLex(string $key, int $min, int $max, int $offset = null, int $limit = null): array {
        return $this->redis->zRevRangeByLex($key, $min, $max, $offset, $limit);
    }

    public function zCount(string $key, string $start, string $end): int {
        return $this->redis->zCount($key, $start, $end);
    }

    public function zRemRangeByScore(string $key, float|string $start, float|string $end): int {
        return $this->redis->zRemRangeByScore($key, $start, $end);
    }

    public function zRemRangeByRank(string $key, int $start, int $end): int {
        return $this->redis->zRemRangeByRank($key, $start, $end);
    }

    public function zCard(string $key): int {
        return $this->redis->zCard($key);
    }

    public function zScore(string $key, mixed $member): float|bool {
        return $this->redis->zScore($key, $member);
    }

    public function zRank(string $key, mixed $member): int|false {
        return $this->redis->zRank($key, $member);
    }

    public function zRevRank(string $key, mixed $member): int|false {
        return $this->redis->zRevRank($key, $member);
    }

    public function zIncrBy(string $key, float $value, string $member): float {
        return $this->redis->zIncrBy($key, $value, $member);
    }

    public function zUnionStore(string $output, array $zSetKeys, ?array $weights = null, string $aggregateFunction = 'SUM'): int {
        return $this->redis->zUnionStore($output, $zSetKeys, $weights, $aggregateFunction);
}

    public function zInterStore(string $output, array $zSetKeys, array $weights = null, string $aggregateFunction = 'SUM'): int {
        return $this->redis->zInterStore($output, $zSetKeys, $weights, $aggregateFunction);
    }

    public function zScan(string $key, int &$iterator, string $pattern = null, int $count = 0): array|false {
        return $this->redis->zScan($key, $iterator, $pattern, $count);
    }

    public function bzPopMax(string|array $key1, string|array $key2, int $timeout): array {
        return $this->redis->bzPopMax($key1, $key2, $timeout);
    }

    public function bzPopMin(string|array $key1, string|array $key2, int $timeout): array {
        return $this->redis->bzPopMin($key1, $key2, $timeout);
    }

    public function zPopMax(string $key, int $count = 1): array {
        return $this->redis->zPopMax($key, $count);
    }

    public function zPopMin(string $key, int $count = 1): array {
        return $this->redis->zPopMin($key, $count);
    }

    public function hSet(string $key, string $hashKey, mixed $value): int|bool {
        return $this->redis->hSet($key, $hashKey, $value);
    }

    public function hSetNx(string $key, string $hashKey, mixed $value): bool {
        return $this->redis->hSetNx($key, $hashKey, $value);
    }

    public function hGet(string $key, string $hashKey): mixed {
        return $this->redis->hGet($key, $hashKey);
    }

    public function hLen(string $key): int|false {
        return $this->redis->hLen($key);
    }

    public function hDel(string $key, string $hashKey1, string ...$otherHashKeys): int|bool {
        return $this->redis->hDel($key, $hashKey1, ...$otherHashKeys);
    }

    public function hKeys(string $key): array {
        return $this->redis->hKeys($key);
    }

    public function hVals(string $key): array {
        return $this->redis->hVals($key);
    }

    public function hGetAll(string $key): array {
        return $this->redis->hGetAll($key);
    }

    public function hExists(string $key, string $hashKey): bool {
        return $this->redis->hExists($key, $hashKey);
    }

    public function hIncrBy(string $key, string $hashKey, int $value): int {
        return $this->redis->hIncrBy($key, $hashKey, $value);
    }

    public function hIncrByFloat(string $key, string $field, float $increment): float {
        return $this->redis->hIncrByFloat($key, $field, $increment);
    }

    public function hMSet(string $key, array $hashKeys): bool {
        return $this->redis->hMSet($key, $hashKeys);
    }

    public function hMGet(string $key, array $hashKeys): array {
        return $this->redis->hMGet($key, $hashKeys);
    }

    public function hScan(string $key, int &$iterator, string $pattern = null, int $count = 0): array {
        return $this->redis->hScan($key, $iterator, $pattern, $count);
    }

    public function hStrLen(string $key, string $field): int {
        return $this->redis->hStrLen($key, $field);
    }

    public function geoadd(string $key, float $longitude, float $latitude, string $member): int {
        return $this->redis->geoadd($key, $longitude, $latitude, $member);
    }

    public function geohash(string $key, string ...$member): array {
        return $this->redis->geohash($key, ...$member);
    }

    public function geopos(string $key, string $member): array {
        return $this->redis->geopos($key, $member);
    }

    public function geodist(string $key, string $member1, string $member2, string $unit = null): float {
        return $this->redis->geodist($key, $member1, $member2, $unit);
    }

    public function georadius(string $key, float $longitude, float $latitude, float $radius, string $unit, array $options = null): mixed {
        return $this->redis->georadius($key, $longitude, $latitude, $radius, $unit, $options);
    }

    public function georadiusbymember(string $key, string $member, float $radius, string $units, array $options = null): array {
        return $this->redis->georadiusbymember($key, $member, $radius, $units, $options);
    }

    public function config(string $operation, string $key, mixed $value): array {
        return $this->redis->config($operation, $key, $value);
    }

    public function eval(string $script, array $args = [], int $numKeys = 0): mixed {
        return $this->redis->eval($script, $args, $numKeys);
    }

    public function evalSha(string $scriptSha, array $args = [], int $numKeys = 0): mixed {
        return $this->redis->evalSha($scriptSha, $args, $numKeys);
    }

    public function script(string $command, string $script): mixed {
        return $this->redis->script($command, $script);
    }

    public function getLastError(): string|null {
        return $this->redis->getLastError();
    }

    public function clearLastError(): bool {
        return $this->redis->clearLastError();
    }

    public function client(string $command, string $value = ''): mixed {
        return $this->redis->client($command, $value);
    }

    public function dump(string $key): string|false {
        return $this->redis->dump($key);
    }

    public function restore(string $key, int $ttl, string $value): bool {
        return $this->redis->restore($key, $ttl, $value);
    }

    public function migrate(string $host, int $port, string $key, int $db, int $timeout, bool $copy = false, bool $replace = false): bool {
        return $this->redis->migrate($host, $port, $key, $db, $timeout, $copy, $replace);
    }

    public function time(): array {
        return $this->redis->time();
    }

    public function scan(int &$iterator, string $pattern = null, int $count = 0): array|false {
        return $this->redis->scan($iterator, $pattern, $count);
    }

    public function pfAdd(string $key, array $elements): bool {
        return $this->redis->pfAdd($key, $elements);
    }

    public function pfCount(string|array $key): int {
        return $this->redis->pfCount($key);
    }

    public function pfMerge(string $destKey, array $sourceKeys): bool {
        return $this->redis->pfMerge($destKey, $sourceKeys);
    }

    public function rawCommand(string $command, mixed $arguments): mixed {
        return $this->redis->rawCommand($command, $arguments);
    }

    public function getMode(): int {
        return $this->redis->getMode();
    }

    public function xAck(string $stream, string $group, array $messages): int {
        return $this->redis->xAck($stream, $group, $messages);
    }

    public function xAdd(string $key, string $id, array $messages, int $maxLen = 0, bool $isApproximate = false): string {
        return $this->redis->xAdd($key, $id, $messages, $maxLen, $isApproximate);
    }

    public function xClaim(string $key, string $group, string $consumer, int $minIdleTime, array $ids, array $options = []): array {
        return $this->redis->xClaim($key, $group, $consumer, $minIdleTime, $ids, $options);
    }

    public function xDel(string $key, array $ids): int {
        return $this->redis->xDel($key, $ids);
    }

    public function xGroup(string $operation, string $key, string $group, string $msgId = '', bool $mkStream = false): mixed {
        return $this->redis->xGroup($operation, $key, $group, $msgId, $mkStream);
    }

    public function xInfo(string $operation, string $stream, string $group): mixed {
        return $this->redis->xInfo($operation, $stream, $group);
    }

    public function xLen(string $stream): int {
        return $this->redis->xLen($stream);
    }

    public function xPending(string $stream, string $group, string $start = null, string $end = null, int $count = null, string $consumer = null): array {
        return $this->redis->xPending($stream, $group, $start, $end, $count, $consumer);
    }

    public function xRange(string $stream, string $start, string $end, int $count = null): array {
        return $this->redis->xRange($stream, $start, $end, $count);
    }

    public function xRead(array $streams, int|string $count = null, int|string $block = null): array {
        return $this->redis->xRead($streams, $count, $block);
    }

    public function xReadGroup(string $group, string $consumer, array $streams, int $count = null, int $block = null): array {
        return $this->redis->xReadGroup($group, $consumer, $streams, $count, $block);
    }

    public function xRevRange(string $stream, string $end, string $start, int $count = null): array {
        return $this->redis->xRevRange($stream, $end, $start, $count);
    }

    public function xTrim(string $stream, int $maxLen, bool $isApproximate): int {
        return $this->redis->xTrim($stream, $maxLen, $isApproximate);
    }

    public function sAddArray(string $key, array $values): int|bool {
        return $this->redis->sAddArray($key, $values);
    }
}