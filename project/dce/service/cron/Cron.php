<?php
namespace dce\service\cron;

use dce\model\Model;
use dce\model\Property;
use dce\model\Validator;

class Cron extends Model {
    public const DEFAULT_ID_PROP = 'default';

    public const STATUS_WAITING = 'waiting';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';

    private const REGULAR_TIME_PART = '((?:(?:,|^)\d+(?:-\d+)?)+|\*)';
    private const REGULAR_TIME = '/^' .self::REGULAR_TIME_PART. '(?:\/(\d+))?$/';
    private const REGULAR_TIME_ONLY = '/^' .self::REGULAR_TIME_PART. '$/';

    private const UNIT_MINUTE = 'minute';
    private const UNIT_HOUR = 'hour';
    private const UNIT_DAY = 'day';
    private const UNIT_MONTH = 'month';
    private const UNIT_WEEK = 'week';

    #[Property, Validator(Validator::RULE_REQUIRED)]
    public string $id;

    #[Property, Validator(Validator::RULE_REQUIRED_EMPTY), Validator(Validator::RULE_REGULAR, regexp: self::REGULAR_TIME)]
    public array|string $minute;

    #[Property, Validator(Validator::RULE_REQUIRED_EMPTY), Validator(Validator::RULE_REGULAR, regexp: self::REGULAR_TIME)]
    public array|string $hour;

    #[Property, Validator(Validator::RULE_REQUIRED), Validator(Validator::RULE_REGULAR, regexp: self::REGULAR_TIME)]
    public array|string $day;

    #[Property, Validator(Validator::RULE_REQUIRED), Validator(Validator::RULE_REGULAR, regexp: self::REGULAR_TIME_ONLY)]
    public array|string $month;

    #[Property, Validator(Validator::RULE_REQUIRED), Validator(Validator::RULE_REGULAR, regexp: self::REGULAR_TIME_ONLY)]
    public array|string $week;

    #[Property, Validator(Validator::RULE_REQUIRED)]
    public string|null $command;

    #[Property]
    public bool $enabled;

    #[Property]
    public bool $runOnStart;

    public int $loopInterval = 0;

    public string $status = self::STATUS_WAITING;

    public int $lastRun = 0;

    public function parse(): void {
        $this->valid();
        preg_match(self::REGULAR_TIME, $this->minute, $parts) && $this->minute = $this->parseUnit($parts[1], $parts[2] ?? 0, self::UNIT_MINUTE);
        preg_match(self::REGULAR_TIME, $this->hour, $parts) && $this->hour = $this->parseUnit($parts[1], $parts[2] ?? 0, self::UNIT_HOUR);
        preg_match(self::REGULAR_TIME, $this->day, $parts) && $this->day = $this->parseUnit($parts[1], $parts[2] ?? 0, self::UNIT_DAY);
        preg_match(self::REGULAR_TIME, $this->month, $parts) && $this->month = $this->parseUnit($parts[1], 0, self::UNIT_MONTH);
        preg_match(self::REGULAR_TIME, $this->week, $parts) && $this->week = $this->parseUnit($parts[1], 0, self::UNIT_WEEK);
    }

    private function parseUnit(string $time, int $interval, string $unit): array {
        static $unitMultiple = [self::UNIT_MINUTE => 1, self::UNIT_HOUR => 60, self::UNIT_DAY => 1440, self::UNIT_MONTH => 0, self::UNIT_WEEK => 0];
        $this->loopInterval += $interval * $unitMultiple[$unit];
        return '*' === $time ? [] : array_map(function ($threshold) use($unit) {
            $isRange = str_contains($threshold, '-');
            [$from, $to] = $isRange ? explode('-', $threshold) : [$threshold, $threshold];
            $to = match($unit) {
                self::UNIT_MINUTE => $to > 59 ? 59 : $to,
                self::UNIT_HOUR => $to > 23 ? 23 : $to,
                self::UNIT_DAY => $to > 31 ? 31 : $to, // 匹配时需注意若设置值大于月份天数，则需在最后天匹配
                self::UNIT_MONTH => $to > 12 ? 12 : $to,
                self::UNIT_WEEK => $to > 6 ? 0 : $to,
            };
            return $isRange ? [(int) $from, (int) $to] : (int) $to;
        }, explode(',', $time));
    }

    public function format(): string {
        return sprintf('%s %s;%s;%s;%s;%s/%s %s %s', $this->id, $this->formatUnit($this->minute), $this->formatUnit($this->hour),
            $this->formatUnit($this->day), $this->formatUnit($this->month), $this->formatUnit($this->week), $this->loopInterval,
            $this->status, $this->command);
    }

    private function formatUnit(array $unit): string {
        return $unit ? implode(',', array_map(fn($u) => is_array($u) ? implode('-', $u) : $u, $unit)) : '*';
    }
}