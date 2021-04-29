<?php
/**
 * Author: Drunk
 * Date: 2016-12-10 1:28
 */

namespace dce\base;

use dce\i18n\Language;
use dce\loader\ClassDecorator;
use Stringable;
use Throwable;

class Exception extends \Exception implements ClassDecorator {
    private Language $langMessage;

    /**
     * 异常构造方法
     * @param int|string|Stringable $message {int: 异常状态码, 或异常多语种文本ID, string|Stringable: 异常消息}
     * @param int $code 异常状态码
     * @param Throwable|null $previous
     */
    public function __construct(int|string|Stringable $message = '', int $code = 0, Throwable $previous = null) {
        if (! $code && is_int($message)) {
            $code = $message;
            $message = '';
        }
        if ($code && ! $message) {
            $message = Language::find($code) ?? '';
        }
        if ($message instanceof Language) {
            $this->langMessage = $message;
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * 格式化异常消息
     * @param string|Stringable ...$parameters
     * @return $this
     */
    public function format(string|Stringable ...$parameters): static {
        if (isset($this->langMessage)) {
            $this->message = $this->langMessage->format(... $parameters);
        } else {
            $this->message = sprintf($this->message, ... $parameters);
        }
        return $this;
    }

    /**
     * 指定消息语种
     * @param string $lang
     * @return $this
     * @throws BaseException
     */
    public function lang(string $lang): static {
        if (! isset($this->langMessage)) {
            throw new BaseException(BaseException::MESSAGE_NOT_LANGUAGE);
        }
        $this->message = $this->langMessage->lang($lang);
        return $this;
    }

    public static function init() {
        set_exception_handler(function(Throwable $e) {
            // header("HTTP/1.1 500 Internal Server Error");
            test(
                get_class($e),
                'Code:' . $e->getCode() . ' ' . $e->getMessage(),
                $e->getFile(),
                'Line:' . $e->getLine(),
                $e->getTrace(),
                $e->getMessage()
            );
        });
    }
}
