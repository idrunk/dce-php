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
    /**
     * @var string[] 可抛出到用户端的异常码列表
     * <pre>
     * 单异常码：公开单个异常，如 [10000, 10001]
     * 横杠分隔的异常码：公开该区间（同类中的），如 ['10000-10010']
     * 头/尾横杠异常码：公开头/尾区间（同类中的），如 ['10000-']
     * 单横杠：全公开（同类中的），如 ['-']
     * </pre>
     */
    protected static array $openly = [];

    /** @var string[] 不能抛出到用户端的异常码列表 */
    protected static array $closed = [];

    /** @var string[] Http状态码，匹配的状态码在自动抛出时会使用httpException方法抛出  */
    protected static array $http = [];

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

    /**
     * 判断异常是否可抛出到用户端
     * @param Throwable $throwable
     * @return bool
     */
    public static function isOpenly(Throwable $throwable): bool {
        // 实现了Openly接口或者符合openly规则的，且不符合closed规则的异常，为可抛出到用户端的异常
        return $throwable instanceof self && ($throwable instanceof Openly || self::rulesMatch($throwable::$openly, $throwable->getCode())) && ! self::rulesMatch($throwable::$closed, $throwable->getCode());
    }

    /**
     * 判断是否Http状态码
     * @param Throwable $throwable
     * @return bool
     */
    public static function isHttp(Throwable $throwable): bool {
        // 实现了Openly接口或者符合openly规则的，且不符合closed规则的异常，为可抛出到用户端的异常
        return $throwable instanceof self && self::rulesMatch($throwable::$http, $throwable->getCode());
    }

    /**
     * 判断异常码是否与配置的某规则匹配
     * @param array $rules
     * @param int $needle
     * @return bool
     */
    private static function rulesMatch(array $rules, int $needle): bool {
        if ($rules && $needle) {
            foreach ($rules as $rule) {
                if (! is_numeric($rule) && ! str_contains($rule, '-')) {
                    // 如果当前不是数字或者非区间，则视为无效项，则跳过
                    continue;
                }
                $range = explode('-', $rule);
                $from = $range[0] ?: 0;
                $to = ($range[1] ?? 0) ?: 0;
                if (match(true) {
                    $from > 0 && $to > 0 => $needle >= $from && $needle <= $to,
                    $from > 0 => $needle >= $from,
                    $to > 0 => $needle <= $to,
                    default => true,
                }) {
                    return true; // 如果当前值在区间内，则返回真
                }
            }
        }
        return false;
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
