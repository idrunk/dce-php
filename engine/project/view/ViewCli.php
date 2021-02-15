<?php
/**
 * Author: Drunk
 * Date: 2017-4-23 11:56
 */

namespace dce\project\view;

use Stringable;

abstract class ViewCli extends View {
    public function input(string $label = '', int $size = 1024): string {
        echo $label;
        return rtrim(fgets(STDIN, $size), "\n\r");
    }

    /**
     * 打印变量值
     * @param mixed $value
     * @param string $suffix
     */
    public function print(mixed $value, string $suffix = "\n"): void {
        print_r($value);
        echo $suffix;
    }

    /**
     * 格式化并打印变量值
     * @param string $format
     * @param mixed ...$arguments
     */
    public function printf(string $format, ... $arguments): void {
        foreach ($arguments as $k => $argument) {
            if (! is_string($argument)) {
                $arguments[$k] = json_encode($argument, JSON_UNESCAPED_UNICODE);
            }
        }
        echo sprintf($format, ... $arguments);
    }
}
