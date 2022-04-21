<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/10/23 11:00
 */

namespace dce\service\cron;

use dce\base\CoverType;
use dce\base\Exception;
use dce\base\SwooleUtility;
use dce\Dce;
use dce\i18n\Language;
use dce\loader\attr\Singleton;
use dce\loader\Decorator;
use dce\log\LogManager;
use drunk\Structure;

abstract class Crontab implements Decorator {
    private const IntervalMinutes = 1;

    /** @var Cron[] 配置实例集 */
    private array $tasks;

    private static Language|array $langStarted = ["任务计划服务已开启.", "Cron service started."];

    protected function __construct() {
        $rules = Dce::$config->cron;
        $default = Cron::from($rules[Cron::DEFAULT_ID_PROP])->extract();
        unset($rules[Cron::DEFAULT_ID_PROP]);
        $this->tasks = array_reduce(array_keys($rules), function($m, $k) use($rules, $default) {
            $t = Cron::from($rules[$k])->apply($default, CoverType::Ignore);
            $t->id = $k;
            $t->parse();
            return array_merge($m, $t->enabled ? [$k => $t] : []);
        }, []);
    }

    public function getTasks(): array {
        return $this->tasks;
    }

    private function enable(): void {
        if (! $this->tasks) return;

        LogManager::dce(self::$langStarted);
        $this->cron();
    }

    private function cron(): void {
        [$minute, $hour, $day, $month, $week] = explode(',', date('i,H,d,m,w'));
        foreach ($this->tasks as $task) {
            $now = ceil(time() / 60);
            if ($task->status !== Cron::STATUS_RUNNING && self::match($task, $minute, $hour, $day, $month, $week)
                && ($task->loopInterval < 1 || ($task->lastRun < 1 ? ($task->lastRun = $now) && $task->runOnStart : $now - $task->lastRun >= $task->loopInterval))
            ) $this->run($task, $now);
        }
        static::sleep();
        $this->cron();
    }

    private static function match(Cron $task, int $minute, int $hour, int $day, int $month, int $week): bool {
        return self::matchRule($task->minuteRange, $minute) && self::matchRule($task->hourRange, $hour)
            && self::matchRule($task->dayRange, $day) && self::matchRule($task->monthRange, $month) && self::matchRule($task->weekRange, $week);
    }

    private static function matchRule(array $rules, int $time): bool {
        return ! $rules || false !== Structure::arraySearch(fn($rule) => is_array($rule) ? $time >= $rule[0] && $time <= $rule[1] : $time === $rule, $rules);
    }

    protected static function getIntervalSeconds(): int {
        $interval = self::IntervalMinutes * 60 - date('s'); // 尽量保证整分启动
        return $interval < 20 ? $interval + 60 : $interval;
    }

    public function run(Cron|string $task, int $now): void {
        if (is_string($task)) {
            ! key_exists($task, $this->tasks) && throw new Exception((new Language('未配置ID为 %s 的任务', 1119))->format($task));
            $task = $this->tasks[$task];
        }
        $task->status = Cron::STATUS_RUNNING;
        $task->lastRun = $now;
        LogManager::cron($task, $this->tasks);
        ['output' => $output, 'code' => $code] = static::exec($task->command);
        $task->status = Cron::STATUS_COMPLETED;
        LogManager::cronDone($task, $code > 0 ? "$output\nCode: $code" : rtrim($output));
    }

    public function showLog(bool $showStatus): string {
        return LogManager::showCron($showStatus);
    }

    /**
     * @param string $command
     * @return array{output: string, code: int, signal: ?int}
     */
    abstract protected static function exec(string $command): array;

    abstract protected static function sleep(): void;

    public static function inst(): static {
        $class = SwooleUtility::inCoroutine() ? CrontabCoroutine::class : CrontabBasic::class;
        return Singleton::gen(fn() => new $class, $class);
    }

    public static function start(): void {
         self::inst()->enable();
    }
}