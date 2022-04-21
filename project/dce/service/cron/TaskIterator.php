<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-11-18 21:09
 */

namespace dce\service\cron;

final class TaskIterator {
    private function __contruct() {}

    /**
     * @template T
     * @param callable(string|int): list<T> $supplier
     * @param callable(T, string|int, int): void $consumer
     * @param callable(T, string|int): void $progressLogger
     * @param string|int $refCursor
     */
    public static function batchIterate(callable $supplier, callable $consumer, callable $progressLogger, string|int & $refCursor): void {
        $prevHash = null;
        // 若取到与前一轮一样的数据，则表示已取完，需退出循环
        while (($calcList = call_user_func_array($supplier, [$refCursor])) && $prevHash !== $currentHash = json_encode($calcList[$lastKey = array_key_last($calcList)])) {
            $prevHash = $currentHash;
            call_user_func_array($consumer, [$calcList, & $refCursor, $lastKey]);
            call_user_func_array($progressLogger, [$calcList[$lastKey], & $refCursor]);
        }
    }

    /**
     * @template T
     * @param callable(string|int): list<T> $supplier
     * @param callable(T, string|int): void $consumer
     * @param callable(T, string|int): void $progressLogger
     * @param string|int $refCursor
     * @param int $stepModToLog
     */
    public static function batchStepIterate(callable $supplier, callable $consumer, callable $progressLogger, string|int & $refCursor, int $stepModToLog = 16): void {
        self::batchIterate($supplier, function(array $calcList, string|int & $refCursor, int $lastKey) use($consumer, $progressLogger, $stepModToLog) {
            foreach ($calcList as $k => $item) {
                call_user_func_array($consumer, [$item, & $refCursor]);
                (($k && ! ($k % $stepModToLog)) && $k !== $lastKey) && call_user_func_array($progressLogger, [$item, & $refCursor]);
            }
        }, $progressLogger, $refCursor);
    }
}