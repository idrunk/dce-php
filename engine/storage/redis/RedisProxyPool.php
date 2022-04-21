<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-12-16 20:59
 */

namespace dce\storage\redis;

use dce\Dce;
use dce\pool\ChannelAbstract;
use Redis;
use Swoole\Coroutine\WaitGroup;

class RedisProxyPool extends RedisProxy {
    private static RedisPool|null $pool;

    protected function __construct(int $index, bool $noSerialize) {
        self::$pool ??= RedisPool::inst()->setConfigs(Dce::$config->redis);
        $this->redis = self::$pool->fetch();
        parent::__construct($index, $noSerialize);
    }

    public function __destruct() {
        self::$pool?->put($this->redis);
        parent::__destruct();
    }

    private function retryableContainer(callable $callable, mixed &... $args): mixed {
        $waitGroup = new WaitGroup;
        $thrownChannel = ChannelAbstract::autoNew(64);
        $result = null;
        self::$pool->retryableContainer(function() use(& $result, $callable, &$args) { $result = call_user_func($callable, ... $args); }, $thrownChannel, $waitGroup);
        $waitGroup->wait();
        if (! $thrownChannel->isEmpty()) {
            unset($this->redis);
            self::$pool = null;
            throw $thrownChannel->pop();
        }
        return $result;
    }

    public function swapdb(int $db1, int $db2): bool {
        return $this->retryableContainer(parent::swapdb(...), $db1, $db2);
    }

    public function setOption(int $option, mixed $value): bool {
        return $this->retryableContainer(parent::setOption(...), $option, $value);
    }

    public function getOption(int $option): mixed {
        return $this->retryableContainer(parent::getOption(...), $option);
    }

    public function ping(string $message = null): bool|string {
        return $this->retryableContainer(parent::ping(...), $message);
    }

    public function echo(string $message): string {
        return $this->retryableContainer(parent::echo(...), $message);
    }

    public function get(string $key): mixed {
        return $this->retryableContainer(parent::get(...), $key);
    }

    public function set(string $key, mixed $value, int|array $timeout = null): bool {
        return $this->retryableContainer(parent::set(...), $key, $value, $timeout);
    }

    public function setex(string $key, int $ttl, mixed $value): bool {
        return $this->retryableContainer(parent::setex(...), $key, $ttl, $value);
    }

    public function psetex(string $key, int $ttl, mixed $value): bool {
        return $this->retryableContainer(parent::psetex(...), $key, $ttl, $value);
    }

    public function setnx(string $key, mixed $value): bool {
        return $this->retryableContainer(parent::setnx(...), $key, $value);
    }

    public function del(int|array|string $key1, string|int ...$otherKeys): int {
        return $this->retryableContainer(parent::del(...), $key1, ...$otherKeys);
    }

    public function unlink(array|string $key1, string $key2 = null, string $key3 = null): int {
        return $this->retryableContainer(parent::unlink(...), $key1, $key2, $key3);
    }

    public function multi(int $mode = Redis::MULTI): Redis {
        return $this->retryableContainer(parent::multi(...), $mode);
    }

    public function pipeline(): Redis {
        return $this->retryableContainer(parent::pipeline(...));
    }

    public function exec(): array|null {
        return $this->retryableContainer(parent::exec(...));
    }

    public function discard(): void {
        $this->retryableContainer(parent::discard(...));
    }

    public function watch(array|string $key): void {
        $this->retryableContainer(parent::watch(...), $key);
    }

    public function unwatch(): void {
        $this->retryableContainer(parent::unwatch(...));
    }

    public function subscribe(array $channels, array|string $callback): mixed {
        return $this->retryableContainer(parent::subscribe(...), $channels, $callback);
    }

    public function psubscribe(array $patterns, array|string $callback): mixed {
        return $this->retryableContainer(parent::psubscribe(...), $patterns, $callback);
    }

    public function publish(string $channel, string $message): int {
        return $this->retryableContainer(parent::publish(...), $channel, $message);
    }

    public function pubsub(string $keyword, array|string $argument): array|int {
        return $this->retryableContainer(parent::pubsub(...), $keyword, $argument);
    }

    public function unsubscribe(array $channels = null): void {
        $this->retryableContainer(parent::unsubscribe(...), $channels);
    }

    public function punsubscribe(array $patterns = null): void {
        $this->retryableContainer(parent::punsubscribe(...), $patterns);
    }

    public function exists(array|string $key): int|bool {
        return $this->retryableContainer(parent::exists(...), $key);
    }

    public function incr(string $key): int {
        return $this->retryableContainer(parent::incr(...), $key);
    }

    public function incrByFloat(string $key, float $increment): float {
        return $this->retryableContainer(parent::incrByFloat(...), $key, $increment);
    }

    public function incrBy(string $key, int $value): int {
        return $this->retryableContainer(parent::incrBy(...), $key, $value);
    }

    public function decr(string $key): bool {
        return $this->retryableContainer(parent::decr(...), $key);
    }

    public function decrBy(string $key, int $value): int {
        return $this->retryableContainer(parent::decrBy(...), $key, $value);
    }

    public function lPush(string $key, mixed ...$value1): int|false {
        return $this->retryableContainer(parent::lPush(...), $key, ...$value1);
    }

    public function rPush(string $key, mixed ...$value1): int|false {
        return $this->retryableContainer(parent::rPush(...), $key, ...$value1);
    }

    public function lPushx(string $key, mixed $value): int|false {
        return $this->retryableContainer(parent::lPushx(...), $key, $value);
    }

    public function rPushx(string $key, mixed $value): int|false {
        return $this->retryableContainer(parent::rPushx(...), $key, $value);
    }

    public function lPop(string $key): mixed {
        return $this->retryableContainer(parent::lPop(...), $key);
    }

    public function rPop(string $key): mixed {
        return $this->retryableContainer(parent::rPop(...), $key);
    }

    public function blPop(array|string $keys, int $timeout): array {
        return $this->retryableContainer(parent::blPop(...), $keys, $timeout);
    }

    public function brPop(array|string $keys, int $timeout): array {
        return $this->retryableContainer(parent::brPop(...), $keys, $timeout);
    }

    public function lLen(string $key): int|bool {
        return $this->retryableContainer(parent::lLen(...), $key);
    }

    public function lIndex(string $key, int $index): mixed {
        return $this->retryableContainer(parent::lIndex(...), $key, $index);
    }

    public function lSet(string $key, int $index, string $value): bool {
        return $this->retryableContainer(parent::lSet(...), $key, $index, $value);
    }

    public function lRange(string $key, int $start, int $end): array {
        return $this->retryableContainer(parent::lRange(...), $key, $start, $end);
    }

    public function lTrim(string $key, int $start, int $stop): array|false {
        return $this->retryableContainer(parent::lTrim(...), $key, $start, $stop);
    }

    public function lRem(string $key, string $value, int $count): int|bool {
        return $this->retryableContainer(parent::lRem(...), $key, $value, $count);
    }

    public function lInsert(string $key, int $position, string $pivot, mixed $value): int {
        return $this->retryableContainer(parent::lInsert(...), $key, $position, $pivot, $value);
    }

    public function sAdd(string $key, mixed ...$value1): int|bool {
        return $this->retryableContainer(parent::sAdd(...), $key, ...$value1);
    }

    public function sRem(string $key, mixed ...$member1): int {
        return $this->retryableContainer(parent::sRem(...), $key, ...$member1);
    }

    public function sMove(string $srcKey, string $dstKey, mixed $member): bool {
        return $this->retryableContainer(parent::sMove(...), $srcKey, $dstKey, $member);
    }

    public function sIsMember(string $key, mixed $value): bool {
        return $this->retryableContainer(parent::sIsMember(...), $key, $value);
    }

    public function sCard(string $key): int {
        return $this->retryableContainer(parent::sCard(...), $key);
    }

    public function sPop(string $key, int $count = 1): mixed {
        return $this->retryableContainer(parent::sPop(...), $key, $count);
    }

    public function sRandMember(string $key, int $count = 1): mixed {
        return $this->retryableContainer(parent::sRandMember(...), $key, $count);
    }

    public function sInter(string $key1, string ...$otherKeys): array|false {
        return $this->retryableContainer(parent::sInter(...), $key1, ...$otherKeys);
    }

    public function sInterStore(string $dstKey, string $key1, string ...$otherKeys): int|false {
        return $this->retryableContainer(parent::sInterStore(...), $dstKey, $key1, ...$otherKeys);
    }

    public function sUnion(string $key1, string ...$otherKeys): array {
        return $this->retryableContainer(parent::sUnion(...), $key1, ...$otherKeys);
    }

    public function sUnionStore(string $dstKey, string $key1, string ...$otherKeys): int {
        return $this->retryableContainer(parent::sUnionStore(...), $dstKey, $key1, ...$otherKeys);
    }

    public function sDiff(string $key1, string ...$otherKeys): array {
        return $this->retryableContainer(parent::sDiff(...), $key1, ...$otherKeys);
    }

    public function sDiffStore(string $dstKey, string $key1, string ...$otherKeys): int|false {
        return $this->retryableContainer(parent::sDiffStore(...), $dstKey, $key1, ...$otherKeys);
    }

    public function sMembers(string $key): array {
        return $this->retryableContainer(parent::sMembers(...), $key);
    }

    public function sScan(string $key, int &$iterator, string $pattern = null, int $count = 0): array|false {
        return $this->retryableContainer(parent::sScan(...), $key, $iterator, $pattern, $count);
    }

    public function getSet(string $key, mixed $value): mixed {
        return $this->retryableContainer(parent::getSet(...), $key, $value);
    }

    public function randomKey(): string {
        return $this->retryableContainer(parent::randomKey(...));
    }

    public function select(int $dbIndex): bool {
        return $this->retryableContainer(parent::select(...), $dbIndex);
    }

    public function move(string $key, int $dbIndex): bool {
        return $this->retryableContainer(parent::move(...), $key, $dbIndex);
    }

    public function rename(string $srcKey, string $dstKey): bool {
        return $this->retryableContainer(parent::rename(...), $srcKey, $dstKey);
    }

    public function renameNx(string $srcKey, string $dstKey): bool {
        return $this->retryableContainer(parent::renameNx(...), $srcKey, $dstKey);
    }

    public function expire(string $key, int $ttl): bool {
        return $this->retryableContainer(parent::expire(...), $key, $ttl);
    }

    public function pExpire(string $key, int $ttl): bool {
        return $this->retryableContainer(parent::pExpire(...), $key, $ttl);
    }

    public function expireAt(string $key, int $timestamp): bool {
        return $this->retryableContainer(parent::expireAt(...), $key, $timestamp);
    }

    public function pExpireAt(string $key, int $timestamp): bool {
        return $this->retryableContainer(parent::pExpireAt(...), $key, $timestamp);
    }

    public function keys(string $pattern): array {
        return $this->retryableContainer(parent::keys(...), $pattern);
    }

    public function dbSize(): int {
        return $this->retryableContainer(parent::dbSize(...));
    }

    public function auth(array|string $password): bool {
        return $this->retryableContainer(parent::auth(...), $password);
    }

    public function bgrewriteaof(): bool {
        return $this->retryableContainer(parent::bgrewriteaof(...));
    }

    public function slaveof(string $host = '127.0.0.1', int $port = 6379): bool {
        return $this->retryableContainer(parent::slaveof(...), $host, $port);
    }

    public function slowLog(string $operation, int $length = null): mixed {
        return $this->retryableContainer(parent::slowLog(...), $operation, $length);
    }

    public function object(string $string = '', string $key = ''): string|int|false {
        return $this->retryableContainer(parent::object(...), $string, $key);
    }

    public function save(): bool {
        return $this->retryableContainer(parent::save(...));
    }

    public function bgsave(): bool {
        return $this->retryableContainer(parent::bgsave(...));
    }

    public function lastSave(): int {
        return $this->retryableContainer(parent::lastSave(...));
    }

    public function wait(int $numSlaves, int $timeout): int {
        return $this->retryableContainer(parent::wait(...), $numSlaves, $timeout);
    }

    public function type(string $key): int {
        return $this->retryableContainer(parent::type(...), $key);
    }

    public function append(string $key, mixed $value): int {
        return $this->retryableContainer(parent::append(...), $key, $value);
    }

    public function getRange(string $key, int $start, int $end): string {
        return $this->retryableContainer(parent::getRange(...), $key, $start, $end);
    }

    public function setRange(string $key, int $offset, string $value): int {
        return $this->retryableContainer(parent::setRange(...), $key, $offset, $value);
    }

    public function strlen(string $key): int {
        return $this->retryableContainer(parent::strlen(...), $key);
    }

    public function bitpos(string $key, int $bit, int $start = 0, int $end = null): int {
        return $this->retryableContainer(parent::bitpos(...), $key, $bit, $start, $end);
    }

    public function getBit(string $key, int $offset): int {
        return $this->retryableContainer(parent::getBit(...), $key, $offset);
    }

    public function setBit(string $key, int $offset, bool|int $value): int {
        return $this->retryableContainer(parent::setBit(...), $key, $offset, $value);
    }

    public function bitCount(string $key): int {
        return $this->retryableContainer(parent::bitCount(...), $key);
    }

    public function bitOp(string $operation, string $retKey, string $key1, string ...$otherKeys): int {
        return $this->retryableContainer(parent::bitOp(...), $operation, $retKey, $key1, ...$otherKeys);
    }

    public function flushDB(): bool {
        return $this->retryableContainer(parent::flushDB(...));
    }

    public function flushAll(): bool {
        return $this->retryableContainer(parent::flushAll(...));
    }

    public function sort(string $key, array $option = null): array {
        return $this->retryableContainer(parent::sort(...), $key, $option);
    }

    public function info(string $option = null): array {
        return $this->retryableContainer(parent::info(...), $option);
    }

    public function resetStat(): bool {
        return $this->retryableContainer(parent::resetStat(...));
    }

    public function ttl(string $key): int|bool {
        return $this->retryableContainer(parent::ttl(...), $key);
    }

    public function pttl(string $key): int|bool {
        return $this->retryableContainer(parent::pttl(...), $key);
    }

    public function persist(string $key): bool {
        return $this->retryableContainer(parent::persist(...), $key);
    }

    public function mset(array $array): bool {
        return $this->retryableContainer(parent::mset(...), $array);
    }

    public function mget(array $array): array {
        return $this->retryableContainer(parent::mget(...), $array);
    }

    public function msetnx(array $array): int {
        return $this->retryableContainer(parent::msetnx(...), $array);
    }

    public function rpoplpush(string $srcKey, string $dstKey): mixed {
        return $this->retryableContainer(parent::rpoplpush(...), $srcKey, $dstKey);
    }

    public function brpoplpush(string $srcKey, string $dstKey, int $timeout): mixed {
        return $this->retryableContainer(parent::brpoplpush(...), $srcKey, $dstKey, $timeout);
    }

    public function zAdd(string $key, float|array $options, mixed $score1, mixed $value1 = null, mixed $score2 = null, mixed $value2 = null, mixed $scoreN = null, mixed $valueN = null): int {
        return $this->retryableContainer(parent::zAdd(...), $key, $options, $score1, $value1, $score2, $value2, $scoreN, $valueN);
    }

    public function zRange(string $key, int $start, int $end, bool $withscores = null): array {
        return $this->retryableContainer(parent::zRange(...), $key, $start, $end, $withscores);
    }

    public function zRem(string $key, mixed $member1, mixed ...$otherMembers): int {
        return $this->retryableContainer(parent::zRem(...), $key, $member1, ...$otherMembers);
    }

    public function zRevRange(string $key, int $start, int $end, bool $withscore = null): array {
        return $this->retryableContainer(parent::zRevRange(...), $key, $start, $end, $withscore);
    }

    public function zRangeByScore(string $key, int $start, int $end, array $options = []): array {
        return $this->retryableContainer(parent::zRangeByScore(...), $key, $start, $end, $options);
    }

    public function zRevRangeByScore(string $key, int $start, int $end, array $options = []): array {
        return $this->retryableContainer(parent::zRevRangeByScore(...), $key, $start, $end, $options);
    }

    public function zRangeByLex(string $key, int $min, int $max, int $offset = null, int $limit = null): array|false {
        return $this->retryableContainer(parent::zRangeByLex(...), $key, $min, $max, $offset, $limit);
    }

    public function zRevRangeByLex(string $key, int $min, int $max, int $offset = null, int $limit = null): array {
        return $this->retryableContainer(parent::zRevRangeByLex(...), $key, $min, $max, $offset, $limit);
    }

    public function zCount(string $key, string $start, string $end): int {
        return $this->retryableContainer(parent::zCount(...), $key, $start, $end);
    }

    public function zRemRangeByScore(string $key, float|string $start, float|string $end): int {
        return $this->retryableContainer(parent::zRemRangeByScore(...), $key, $start, $end);
    }

    public function zRemRangeByRank(string $key, int $start, int $end): int {
        return $this->retryableContainer(parent::zRemRangeByRank(...), $key, $start, $end);
    }

    public function zCard(string $key): int {
        return $this->retryableContainer(parent::zCard(...), $key);
    }

    public function zScore(string $key, mixed $member): float|bool {
        return $this->retryableContainer(parent::zScore(...), $key, $member);
    }

    public function zRank(string $key, mixed $member): int|false {
        return $this->retryableContainer(parent::zRank(...), $key, $member);
    }

    public function zRevRank(string $key, mixed $member): int|false {
        return $this->retryableContainer(parent::zRevRank(...), $key, $member);
    }

    public function zIncrBy(string $key, float $value, string $member): float {
        return $this->retryableContainer(parent::zIncrBy(...), $key, $value, $member);
    }

    public function zUnionStore(string $output, array $zSetKeys, ?array $weights = null, string $aggregateFunction = 'SUM'): int {
        return $this->retryableContainer(parent::zUnionStore(...), $output, $zSetKeys, $weights, $aggregateFunction);
    }

    public function zInterStore(string $output, array $zSetKeys, array $weights = null, string $aggregateFunction = 'SUM'): int {
        return $this->retryableContainer(parent::zInterStore(...), $output, $zSetKeys, $weights, $aggregateFunction);
    }

    public function zScan(string $key, int &$iterator, string $pattern = null, int $count = 0): array|false {
        return $this->retryableContainer(parent::zScan(...), $key, $iterator, $pattern, $count);
    }

    public function bzPopMax(array|string $key1, array|string $key2, int $timeout): array {
        return $this->retryableContainer(parent::bzPopMax(...), $key1, $key2, $timeout);
    }

    public function bzPopMin(array|string $key1, array|string $key2, int $timeout): array {
        return $this->retryableContainer(parent::bzPopMin(...), $key1, $key2, $timeout);
    }

    public function zPopMax(string $key, int $count = 1): array {
        return $this->retryableContainer(parent::zPopMax(...), $key, $count);
    }

    public function zPopMin(string $key, int $count = 1): array {
        return $this->retryableContainer(parent::zPopMin(...), $key, $count);
    }

    public function hSet(string $key, string $hashKey, mixed $value): int|bool {
        return $this->retryableContainer(parent::hSet(...), $key, $hashKey, $value);
    }

    public function hSetNx(string $key, string $hashKey, mixed $value): bool {
        return $this->retryableContainer(parent::hSetNx(...), $key, $hashKey, $value);
    }

    public function hGet(string $key, string $hashKey): mixed {
        return $this->retryableContainer(parent::hGet(...), $key, $hashKey);
    }

    public function hLen(string $key): int|false {
        return $this->retryableContainer(parent::hLen(...), $key);
    }

    public function hDel(string $key, string $hashKey1, string ...$otherHashKeys): int|bool {
        return $this->retryableContainer(parent::hDel(...), $key, $hashKey1, ...$otherHashKeys);
    }

    public function hKeys(string $key): array {
        return $this->retryableContainer(parent::hKeys(...), $key);
    }

    public function hVals(string $key): array {
        return $this->retryableContainer(parent::hVals(...), $key);
    }

    public function hGetAll(string $key): array {
        return $this->retryableContainer(parent::hGetAll(...), $key);
    }

    public function hExists(string $key, string $hashKey): bool {
        return $this->retryableContainer(parent::hExists(...), $key, $hashKey);
    }

    public function hIncrBy(string $key, string $hashKey, int $value): int {
        return $this->retryableContainer(parent::hIncrBy(...), $key, $hashKey, $value);
    }

    public function hIncrByFloat(string $key, string $field, float $increment): float {
        return $this->retryableContainer(parent::hIncrByFloat(...), $key, $field, $increment);
    }

    public function hMSet(string $key, array $hashKeys): bool {
        return $this->retryableContainer(parent::hMSet(...), $key, $hashKeys);
    }

    public function hMGet(string $key, array $hashKeys): array {
        return $this->retryableContainer(parent::hMGet(...), $key, $hashKeys);
    }

    public function hScan(string $key, int &$iterator, string $pattern = null, int $count = 0): array {
        return $this->retryableContainer(parent::hScan(...), $key, $iterator, $pattern, $count);
    }

    public function hStrLen(string $key, string $field): int {
        return $this->retryableContainer(parent::hStrLen(...), $key, $field);
    }

    public function geoadd(string $key, float $longitude, float $latitude, string $member): int {
        return $this->retryableContainer(parent::geoadd(...), $key, $longitude, $latitude, $member);
    }

    public function geohash(string $key, string ...$member): array {
        return $this->retryableContainer(parent::geohash(...), $key, ...$member);
    }

    public function geopos(string $key, string $member): array {
        return $this->retryableContainer(parent::geopos(...), $key, $member);
    }

    public function geodist(string $key, string $member1, string $member2, string $unit = null): float {
        return $this->retryableContainer(parent::geodist(...), $key, $member1, $member2, $unit);
    }

    public function georadius(string $key, float $longitude, float $latitude, float $radius, string $unit, array $options = null): mixed {
        return $this->retryableContainer(parent::georadius(...), $key, $longitude, $latitude, $radius, $unit, $options);
    }

    public function georadiusbymember(string $key, string $member, float $radius, string $units, array $options = null): array {
        return $this->retryableContainer(parent::georadiusbymember(...), $key, $member, $radius, $units, $options);
    }

    public function config(string $operation, string $key, mixed $value): array {
        return $this->retryableContainer(parent::config(...), $operation, $key, $value);
    }

    public function eval(string $script, array $args = [], int $numKeys = 0): mixed {
        return $this->retryableContainer(parent::eval(...), $script, $args, $numKeys);
    }

    public function evalSha(string $scriptSha, array $args = [], int $numKeys = 0): mixed {
        return $this->retryableContainer(parent::evalSha(...), $scriptSha, $args, $numKeys);
    }

    public function script(string $command, string $script): mixed {
        return $this->retryableContainer(parent::script(...), $command, $script);
    }

    public function getLastError(): string|null {
        return $this->retryableContainer(parent::getLastError(...));
    }

    public function clearLastError(): bool {
        return $this->retryableContainer(parent::clearLastError(...));
    }

    public function client(string $command, string $value = ''): mixed {
        return $this->retryableContainer(parent::client(...), $command, $value);
    }

    public function dump(string $key): string|false {
        return $this->retryableContainer(parent::dump(...), $key);
    }

    public function restore(string $key, int $ttl, string $value): bool {
        return $this->retryableContainer(parent::restore(...), $key, $ttl, $value);
    }

    public function migrate(string $host, int $port, string $key, int $db, int $timeout, bool $copy = false, bool $replace = false): bool {
        return $this->retryableContainer(parent::migrate(...), $host, $port, $key, $db, $timeout, $copy, $replace);
    }

    public function time(): array {
        return $this->retryableContainer(parent::time(...));
    }

    public function scan(int &$iterator, string $pattern = null, int $count = 0): array|false {
        return $this->retryableContainer(parent::scan(...), $iterator, $pattern, $count);
    }

    public function pfAdd(string $key, array $elements): bool {
        return $this->retryableContainer(parent::pfAdd(...), $key, $elements);
    }

    public function pfCount(array|string $key): int {
        return $this->retryableContainer(parent::pfCount(...), $key);
    }

    public function pfMerge(string $destKey, array $sourceKeys): bool {
        return $this->retryableContainer(parent::pfMerge(...), $destKey, $sourceKeys);
    }

    public function rawCommand(string $command, mixed $arguments): mixed {
        return $this->retryableContainer(parent::rawCommand(...), $command, $arguments);
    }

    public function getMode(): int {
        return $this->retryableContainer(parent::getMode(...));
    }

    public function xAck(string $stream, string $group, array $messages): int {
        return $this->retryableContainer(parent::xAck(...), $stream, $group, $messages);
    }

    public function xAdd(string $key, string $id, array $messages, int $maxLen = 0, bool $isApproximate = false): string {
        return $this->retryableContainer(parent::xAdd(...), $key, $id, $messages, $maxLen, $isApproximate);
    }

    public function xClaim(string $key, string $group, string $consumer, int $minIdleTime, array $ids, array $options = []): array {
        return $this->retryableContainer(parent::xClaim(...), $key, $group, $consumer, $minIdleTime, $ids, $options);
    }

    public function xDel(string $key, array $ids): int {
        return $this->retryableContainer(parent::xDel(...), $key, $ids);
    }

    public function xGroup(string $operation, string $key, string $group, string $msgId = '', bool $mkStream = false): mixed {
        return $this->retryableContainer(parent::xGroup(...), $operation, $key, $group, $msgId, $mkStream);
    }

    public function xInfo(string $operation, string $stream, string $group): mixed {
        return $this->retryableContainer(parent::xInfo(...), $operation, $stream, $group);
    }

    public function xLen(string $stream): int {
        return $this->retryableContainer(parent::xLen(...), $stream);
    }

    public function xPending(string $stream, string $group, string $start = null, string $end = null, int $count = null, string $consumer = null): array {
        return $this->retryableContainer(parent::xPending(...), $stream, $group, $start, $end, $count, $consumer);
    }

    public function xRange(string $stream, string $start, string $end, int $count = null): array {
        return $this->retryableContainer(parent::xRange(...), $stream, $start, $end, $count);
    }

    public function xRead(array $streams, int|string $count = null, int|string $block = null): array {
        return $this->retryableContainer(parent::xRead(...), $streams, $count, $block);
    }

    public function xReadGroup(string $group, string $consumer, array $streams, int $count = null, int $block = null): array {
        return $this->retryableContainer(parent::xReadGroup(...), $group, $consumer, $streams, $count, $block);
    }

    public function xRevRange(string $stream, string $end, string $start, int $count = null): array {
        return $this->retryableContainer(parent::xRevRange(...), $stream, $end, $start, $count);
    }

    public function xTrim(string $stream, int $maxLen, bool $isApproximate): int {
        return $this->retryableContainer(parent::xTrim(...), $stream, $maxLen, $isApproximate);
    }

    public function sAddArray(string $key, array $values): int|bool {
        return $this->retryableContainer(parent::sAddArray(...), $key, $values);
    }
}