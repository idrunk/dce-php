<?php
/**
 * Author: Drunk
 * Date: 2022-09-09 21:12
 */

namespace dce\base;

use Stringable;

/**
 * 此异常类用于便捷的中断请求响应内容
 */
class ResponseException extends Exception implements Openly {
    public function __construct(Stringable|int|string $message = '', int $code = 0, Exception $previous = null, public array $data = []) {
        parent::__construct($message, $code, $previous);
    }
}