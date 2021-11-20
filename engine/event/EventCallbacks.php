<?php
/**
 * Author: Drunk
 * Date: 2019-1-20 18:25
 */

namespace dce\event;

/**
 * Class EventCallbacks
 * @package dce\event
 */
class EventCallbacks {
    private array $callbacks = [];

    private array $args = [];

    private array $config = [
        'max_trigger_count' => 0, // 一直有效
        'expired_seconds' => 0, // 一直不过期
    ];

    /**
     * EventCallbacks constructor.
     * @param array $config
     * @param array $args
     * @param callable|null $callback
     */
    public function __construct(array $config = [], array $args = [], callable $callback = null) {
        $this->config = array_replace_recursive($this->config, $config);
        $this->args = $args;
        if (is_callable($callback)) {
            $this->push($callback, $this->config['max_trigger_count'], $this->config['expired_seconds']);
        }
    }

    /**
     * @param callable $callback
     * @param int $triggerCount
     * @param int $expiredSeconds
     * @param array $args
     * @return array
     */
    private function package(callable $callback, int $triggerCount = 0, int $expiredSeconds = 0, array $args = []): array {
        return [
            'callback' => $callback,
            'max_trigger_count' => $triggerCount,
            'expired_seconds' => $expiredSeconds,
            'args' => array_merge($this->args, $args),
            'trigger_count' => 0, // 初始化被触发次数
            'package_time' => time(), // 记录打包时间, 供后续处理过期
        ];
    }

    /**
     * 压入回调方法
     * @param callable $callback
     * @param int $triggerCount
     * @param int $expiredSeconds
     * @param array $args
     * @return int
     */
    public function push(callable $callback, int $triggerCount = 0, int $expiredSeconds = 0, array $args = []): int {
        return array_push($this->callbacks, $this->package($callback, $triggerCount, $expiredSeconds, $args));
    }

    /**
     * 插入回调方法到队列首部
     * @param callable $callback
     * @param int $trigger_count
     * @param int $expired_seconds
     * @param array $args
     * @return int
     */
    public function unshift(callable $callback, int $trigger_count = 0, int $expired_seconds = 0, array $args = []): int {
        return array_unshift($this->callbacks, $this->package($callback, $trigger_count, $expired_seconds, $args));
    }

    /**
     * 按回调函数移除队列项
     * @param callable $callback
     * @return bool|null
     */
    public function remove(callable $callback): bool|null {
        foreach ($this->callbacks as $k=>['callback'=>$callable]) {
            if ($callback == $callable) {
                $this->removeByIndex($k);
                return true;
            }
        }
        return null;
    }

    /**
     * 返回队列是否为空
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->callbacks);
    }

    /**
     * 触发回调函数队列
     * @param array $args
     * @return bool
     */
    public function trigger(mixed ... $args): bool {
        foreach ($this->callbacks as $k=>&$v) {
            // 如果设置了过期时间, 且已过期, 则删除回调并跳过
            if ($v['expired_seconds'] > 0 && time() - $v['package_time'] > $v['expired_seconds']) {
                $this->removeByIndex($k);
                continue;
            }
            call_user_func_array($v['callback'], [... $v['args'], ... $args]); // 这里可能无法
            if ($v['max_trigger_count'] > 0) {
                $v['trigger_count'] ++;
                // 如果回调执行次数到达上限, 则删除回调并跳过
                $v['trigger_count'] >= $v['max_trigger_count'] && $this->removeByIndex($k);
            }
        }
        return true;
    }

    /**
     * 按下标移出回调队列项
     * @param int $index
     * @return bool
     */
    private function removeByIndex(int $index): bool {
        unset($this->callbacks[$index]);
        return true;
    }
}
