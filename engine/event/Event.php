<?php
/**
 * Author: Drunk
 * Date: 2019-1-20 16:00
 */

namespace dce\event;

class Event {
    /** @var string Dce静态类初始化完时回调 */
    public const AFTER_DCE_INIT = 'AFTER_DCE_INIT';

    /**
     * Request对象初始化前回调
     * @callable(RawRequest)
     */
    public const BEFORE_ROUTE = 'BEFORE_ROUTE';

    /**
     * Request对象初始化后回调
     * @callable(Request)
     */
    public const AFTER_ROUTE = 'AFTER_ROUTE';

    /**
     * 进入控制器前回调
     * @callable(Request)
     */
    public const BEFORE_CONTROLLER = 'BEFORE_CONTROLLER';

    /**
     * 进入控制器时回调 (控制器实例化后触发)
     * @callable(Controller)
     */
    public const ENTERING_CONTROLLER = 'ENTERING_CONTROLLER';

    /**
     * 控制器执行完毕回调
     * @callable(Controller)
     */
    public const AFTER_CONTROLLER = 'AFTER_RESPONSE';

    public const AFTER_DAEMON = 'AFTER_DAEMON';

    /** @var EventCallbacks[] $events */
    private static array $events = [];

    /**
     * @param string $eventName
     * @return EventCallbacks
     */
    private static function caller(string $eventName): EventCallbacks {
        return self::exists($eventName) ? self::$events[$eventName] : new EventCallbacks();
    }

    /**
     * 绑定事件
     * @param string $eventName
     * @param callable $eventCallable
     * @param array $args
     * @param bool $isPrepend
     * @param int $maxTriggerCount
     * @param int $expiredSeconds
     * @return int
     */
    public static function on(string $eventName, callable $eventCallable, array $args = [], bool $isPrepend = false, int $maxTriggerCount = 0, int $expiredSeconds = 0): int {
        $callbacks = self::caller($eventName);
        $bind = $isPrepend ?
            $callbacks->unshift($eventCallable, $maxTriggerCount, $expiredSeconds, $args):
            $callbacks->push($eventCallable, $maxTriggerCount, $expiredSeconds, $args);
        self::$events[$eventName] = $callbacks;
        return $bind;
    }

    /**
     * 绑定单次触发事件
     * @param string $eventName
     * @param callable $eventCallable
     * @param array $args
     * @param bool $isPrepend
     * @param int $expiredSeconds
     * @return int
     */
    public static function one(string $eventName, callable $eventCallable, array $args = [], bool $isPrepend = false, int $expiredSeconds = 0): int {
        return self::on($eventName, $eventCallable, $args, $isPrepend, 1, $expiredSeconds);
    }

    /**
     * 解绑事件
     * @param string $eventName
     * @param callable|null $eventCallable
     * @return bool|null
     */
    public static function off(string $eventName, callable $eventCallable = null): bool|null {
        $callbacks = self::get($eventName);
        if (! $callbacks) {
            return null;
        }
        if (is_callable($eventCallable)) {
            $callbacks->remove($eventCallable);
            if ($callbacks->isEmpty()) {
                return self::off($eventName);
            }
        }
        unset(self::$events[$eventName]);
        return true;
    }

    /**
     * 触发事件
     * @param string $eventName
     * @param array $args
     * @return bool|null
     */
    public static function trigger(string $eventName, mixed ... $args): bool|null {
        if (! $callbacks = self::get($eventName)) {
            return null;
        }
        $trigger = $callbacks->trigger(... $args);
        if ($callbacks->isEmpty()) {
            self::off($eventName);
        }
        return $trigger;
    }

    /**
     * @param string|null $eventName
     * @return EventCallbacks|EventCallbacks[]|null
     */
    public static function get(string $eventName = null): EventCallbacks|array|null {
        if (is_null($eventName)) {
            return self::$events;
        }
        return self::exists($eventName) ? self::$events[$eventName] : null;
    }

    /**
     * @param string $eventName
     * @return bool
     */
    public static function exists(string $eventName): bool {
        return key_exists($eventName, self::$events);
    }
}
