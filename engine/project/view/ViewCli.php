<?php
/**
 * Author: Drunk
 * Date: 2017-4-23 11:56
 */

namespace dce\project\view;

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
        printf('%s%s', is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), $suffix);
    }

    /**
     * 格式化并打印变量值
     * @param string $format
     * @param mixed ...$arguments
     */
    public function printf(string $format, mixed ... $arguments): void {
        foreach ($arguments as $k => $argument) {
            if (! is_scalar($argument)) {
                $arguments[$k] = json_encode($argument, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }
        printf($format, ... $arguments);
    }
}
