<?php
/**
 * Author: Drunk
 * Date: 2016-12-10 1:28
 */

namespace dce\base;

use dce\Dce;
use dce\i18n\Language;
use dce\loader\Decorator;
use dce\log\LogManager;
use dce\log\MatrixLogger;
use dce\project\Controller;
use dce\project\request\RawRequestHttp;
use dce\project\request\Request;
use dce\project\request\RequestException;
use dce\project\request\RequestManager;
use dce\service\server\RawRequestConnection;
use dce\sharding\middleware\ShardingTransaction;
use Stringable;
use Throwable;

class Exception extends \Exception implements Decorator {
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
    protected static array $http = ['100-600'];

    private Language $langMessage;

    private Request|null $request;

    /**
     * 异常构造方法
     * @param int|string|Stringable $message {int: 异常状态码, 或异常多语种文本ID, string|Stringable: 异常消息}
     * @param int $code 异常状态码
     * @param Throwable|null $previous
     */
    public function __construct(int|string|Stringable $message = '', int $code = 0, Throwable $previous = null) {
        $this->request = RequestManager::current();
        if (! $code && is_int($message)) {
            $code = $message;
            $message = '';
        }
        $code && ! $message && $message = Language::find($code) ?? '';
        ($message instanceof Language) && $this->langMessage = $message;
        parent::__construct($message, $code, $previous);
    }

    /**
     * 格式化异常消息
     * @param string|Stringable ...$parameters
     * @return $this
     */
    public function format(string|Stringable ...$parameters): static {
        $this->message = isset($this->langMessage) ? $this->langMessage->format(... $parameters) : sprintf($this->message, ... $parameters);
        return $this;
    }

    /**
     * 指定消息语种
     * @param string $lang
     * @return $this
     * @throws BaseException
     */
    public function lang(string $lang): static {
        if (! isset($this->langMessage))
            throw new BaseException(BaseException::MESSAGE_NOT_LANGUAGE);
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
                // 如果当前不是数字或者非区间，则视为无效项，则跳过
                if (! is_numeric($rule) && ! str_contains($rule, '-')) continue;
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

    /**
     * @param self $throwable
     * @return Request|null
     */
    private static function getRequest(Throwable $throwable): Request|null {
        return $throwable->request ?? RequestManager::current();
    }

    public static function catchRequest(callable $callable, mixed ... $params): void {
        try {
            call_user_func($callable, ... $params);
        } catch (QuietException) {
            // 拦截安静异常不抛出, 用于如事件回调中抛出异常截停程序并且不抛出异常
        } catch (Throwable $throwable) {
            $isHttp = self::isHttp($throwable);
            $isOpenly = ! $isHttp && self::isOpenly($throwable);
            $isSimple = $isHttp || $isOpenly;

            LogManager::exception($throwable, $isSimple); // 打印及记录日志

            if (! ($request = self::getRequest($throwable)) instanceof Request) return;

            // 尝试回滚请求中的事务（先这么写死吧）
            ShardingTransaction::rollbackRequestThrown($request->id);

            // 如果不是简单异常或开发模式，则响应http500，否则响应异常消息
            $exception = ! ($isSimple || Dce::isDevEnv()) && ($isHttp = true) ? new RequestException(RequestException::INTERNAL_SERVER_ERROR) : $throwable;
            $controller = $request->controller ?? null;
            if ($request->rawRequest instanceof RawRequestHttp) {
                if ($isOpenly) { // 如果是公开异常，则响应异常消息
                    $controller ? self::renderController($controller, $exception, $isHttp) : $request->rawRequest->response(self::render($exception, null));
                } else if ($controller) {
                    self::renderController($controller, $exception, $isHttp);
                } else {
                    $request->rawRequest->status($exception->getCode(), $exception->getMessage());
                    $request->rawRequest->response(self::render($exception, $isHttp || ! Dce::isDevEnv(), true));
                }
            } else if ($request->rawRequest instanceof RawRequestConnection && $request->rawRequest->isResponseMode()) {
                $controller ? self::renderController($controller, $exception, false) : $request->rawRequest->response(self::render($exception, null), $request->rawRequest->path);
            }
        }
    }

    private static function renderController(Controller $controller, Throwable $throwable, bool $isHttp): void {
        $controller->assignStatus('message', $throwable->getMessage());
        if ($throwable instanceof ResponseException) {
            $controller->assignStatus('status', false);
            $controller->assignStatus('code', $throwable->getCode());
            $controller->assignMapping($throwable->data);
            $isHttp ? $controller->httpException($throwable->getCode()) : $controller->render();
        } else {
            $isHttp ? $controller->httpException($throwable->getCode()) : $controller->exception($throwable);
        }
    }

    /**
     * @param callable<Throwable, void>|bool $matched
     * @param callable $callable
     * @param mixed ...$params
     * @throws Throwable
     */
    public static function catchWarning(callable|bool $matched, callable $callable, mixed ... $params): void {
        try {
            call_user_func($callable, ... $params);
        } catch (Throwable $throwable) {
            is_callable($matched) && $matched = call_user_func($matched, $throwable);
            $matched ? LogManager::warning($throwable) : throw $throwable;
        }
    }

    public static function render(Throwable $throwable, bool|null $simple = false, bool $html = false): string {
        return MatrixLogger::applyTime(LogManager::exceptionRender($throwable, $simple, $html), time());
    }

    public static function init() {
        set_exception_handler(fn(Throwable $throwable) => die(self::render($throwable)));
    }
}
