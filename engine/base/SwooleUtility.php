<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/2/29 19:03
 */

namespace dce\base;

use Swoole\Coroutine;
use Swoole\Table;

final class SwooleUtility {
    public static function inSwoole(): bool {
        static $inSwoole;
        if (null === $inSwoole) {
            $inSwoole = extension_loaded('swoole');
        }
        return $inSwoole;
    }

    public static function inCoroutine(): bool {
        return self::inSwoole() && Coroutine::getCid() > 0;
    }

    /**
     * 开启协程钩子
     * @param int $hookFlags
     */
    public static function coroutineHook(int $hookFlags = SWOOLE_HOOK_ALL): void {
        Coroutine::set(['hook_flags' => $hookFlags]);
    }

    /**
     * 协程控流阀, 当协程达道限定值后, 程序执行到此方法时将停留在循环中, 直到有协程处理完任务结束了生命周期时, 被拦截在此的程序才会继续执行, 创建调度新协程
     * @param int $maxCoroutine
     */
    public static function coroutineValve(int $maxCoroutine = 1000): void {
        while (Coroutine::stats()['coroutine_num'] > $maxCoroutine) {
            Coroutine::sleep(0.01);
        }
    }

    /**
     * 跨进程协程锁初始化
     * @throws Exception
     */
    public static function processLockInit(): void {
        self::processLocker(null);
    }

    /**
     * 获取/设置跨进程协程锁定状态
     * @param string|null $identification
     * @param int $maximum
     * @return bool|null
     * @throws Exception
     */
    private static function processLocker(string|null $identification, int $maximum = 1): bool|null {
        /** @var Table $lockerMap */
        static $lockerMap;
        if (null === $lockerMap) {
            self::rootProcessConstraint();
            $lockerMap = new Table(255);
            $lockerMap->column('counter', Table::TYPE_INT);
            $lockerMap->column('maximum', Table::TYPE_INT);
            $lockerMap->column('lock_time', Table::TYPE_INT);
            $lockerMap->create();
            return null;
        }
        $isDelete = $maximum < 1;
        if ($isDelete) {
            // 记录为未锁定状态
            $lockerMap->decr($identification, 'counter');
            return true;
        } else {
            $counter = $lockerMap->get($identification)['counter'] ?? false;
            if ($counter >= $maximum) {
                // 当前为锁定状态时返回false
                return false;
            } else {
                $data = ['lock_time' => time()];
                if (false === $counter) {
                    $data += ['counter' => 0, 'maximum' => $maximum];
                }
                $lockerMap->set($identification, $data);
                // 这里不能合在set中一起操作, 因为在get后可能别的并行程序已经改变了储存值
                $lockerMap->incr($identification, 'counter');
                // 在并行程序中, 可能不同的程序同时取得相同key值, 该值小于锁定上限(即同时取到锁), 所以需要在取到锁后重新校验锁的有效性, 若无效则还原锁定值并返回false
                if ($lockerMap->get($identification)['counter'] > $counter + 1) {
                    $lockerMap->decr($identification, 'counter');
                    return false;
                }
                // 锁定时返回true
                return true;
            }
        }
    }

    /**
     * 跨进程协程锁上锁 (悲观, 自旋, 不可重入)
     * @param string|null $identification 锁标识, 若锁定与解锁不在同一个方法中, 或者同一个方法中多处上锁, 则需手动指定, 解锁时传递相同标识参数
     * @param int $maximum 全局可重入次数
     * @return bool 锁定总是成功, 通过返回值的不同表示获得锁时的状态不同
     * <pre>
     * true: 上锁前无其他锁, 直接锁定
     * false: 锁定前区块正被上把锁锁定, 等待其解锁后才获得这把锁
     * </pre>
     * @throws Exception
     */
    public static function processLock(string|null $identification = null, int $maximum = 1): bool {
        $identification ??= self::defaultIdentification();
        $maximum = $maximum < 1 ? 1 : $maximum;
        $isGetLock = $initialLockState = self::processLocker($identification, $maximum);
        while (false === $isGetLock) {
            Coroutine::sleep(0.01);
            $isGetLock = self::processLocker($identification, $maximum);
        }
        return $initialLockState;
    }

    /**
     * 跨进程协程锁解锁
     * @param string|null $identification 锁标识, 需传入与锁定时相同的标识参数
     * @throws Exception
     */
    public static function processUnlock(string|null $identification = null): void {
        $identification ??= self::defaultIdentification();
        self::processLocker($identification, 0);
    }

    /**
     * 取默认锁标识
     * @return string
     */
    private static function defaultIdentification(): string {
        $context = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? [];
        return md5($context['file'] .':'. ($context['function'] ?? '/'));
    }

    /**
     * 获取/设置协程锁定状态
     * @param string|null $identification
     * @param int $maximum
     * @return bool|null
     */
    private static function coroutineLocker(string|null $identification, int $maximum = 1): bool|null {
        $isDelete = $maximum < 1;
        static $lockerMap = [];
        if ($isDelete) {
            $lockerMap[$identification]['counter'] --;
            return true;
        } else {
            $counter = $lockerMap[$identification]['counter'] ?? false;
            if ($counter >= $maximum) {
                return false;
            } else {
                if (false === $counter) {
                    $lockerMap[$identification] = ['maximum' => $maximum, 'counter' => 0];
                }
                $lockerMap[$identification]['lock_time'] = time();
                $lockerMap[$identification]['counter'] ++;
                return true;
            }
        }
    }

    /**
     * 协程锁上锁 (悲观, 自旋, 不可重入)
     * @param string|null $identification 锁标识, 若锁定与解锁不在同一个方法中, 或者同一个方法中多处上锁, 则需手动指定, 解锁时传递相同标识参数
     * @param int $maximum 同进程可重入次数
     * @return bool 锁定总是成功, 通过返回值的不同表示获得锁时的状态不同
     * <pre>
     * true: 上锁前无其他锁, 直接锁定
     * false: 锁定前区块正被上把锁锁定, 等待其解锁后才获得这把锁
     * </pre>
     */
    public static function coroutineLock(string|null $identification = null, int $maximum = 1): bool {
        $identification ??= self::defaultIdentification();
        $maximum = $maximum < 1 ? 1 : $maximum;
        $isGetLock = $initialLockState = self::coroutineLocker($identification, $maximum);
        while (false === $isGetLock) {
            Coroutine::sleep(0.01);
            $isGetLock = self::coroutineLocker($identification, $maximum);
        }
        return $initialLockState;
    }

    /**
     * 协程锁解锁
     * @param string|null $identification 锁标识, 需传入与锁定时相同的标识参数
     */
    public static function coroutineUnlock(string|null $identification = null): void {
        $identification ??= self::defaultIdentification();
        self::coroutineLocker($identification, 0);
    }

    /**
     * 根进程校验, 在需在根进程中调用的方法调用此方法, 以限制该方法必须在根进程中调用, 否则抛异常
     * @throws Exception
     */
    public static function rootProcessConstraint(): void {
        static $masterPid;
        null === $masterPid && $masterPid = posix_getpid();
        posix_getpid() !== $masterPid && throw new BaseException(BaseException::NEED_ROOT_PROCESS);
    }
}
