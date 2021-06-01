<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/3/23 23:23
 */

namespace dce\project;

use dce\project\render\Renderer;
use dce\project\render\TemplateRenderer;
use dce\project\request\RawRequest;
use dce\project\request\RawRequestHttp;
use dce\project\request\Request;
use dce\service\server\RawRequestConnection;
use Throwable;

class Controller {
    /** @var bool 是否渲染过 */
    public bool $rendered = false;

    protected RawRequest $rawRequest;

    private bool $isResponseMode;

    private Renderer $rendererInstance;

    private array $statusData = [];

    private bool $isHttpException = false;

    /**
     * 控制器构造函数, 为了防止用户在子类重写了构造函数却未调用父构造函数破坏控制器, 所以将其设为了final禁止子类重写, 子类若需构造函数可定义__init方法实现
     * @param Request $request
     */
    final public function __construct(
        public Request $request,
    ) {
        $this->rawRequest = $this->request->rawRequest;
        $this->isResponseMode = $this->rawRequest instanceof RawRequestHttp || ($this->rawRequest instanceof RawRequestConnection && $this->rawRequest->isResponseMode());
        $this->rendererInstance = Renderer::inst($this, $this->isResponseMode);
        $this->__init();
    }

    /** 安全的控制器构造方法, 子类可重写且无需调用父类方法 */
    protected function __init(): void {}

    /**
     * 调用控制器方法
     * @param string $method
     */
    public function call(string $method): void {
        if (! $this->rendered) {
            $this->$method();
            $this->isResponseMode && $this->render(); // 无需给非请求响应模型实现渲染缓存器, 因为他们不能自动渲染, 反正需要手动, 即使需要缓存也得自行实现
        }
    }

    /**
     * 渲染响应
     * @param mixed|false $data
     * @param string|false|null $path
     */
    public function render(mixed $data = false, string|false|null $path = null): void {
        $this->rendererInstance->render($this, $this->isResponseMode, $data, $path);
    }

    /**
     * 直接响应
     * @param mixed|false $data
     * @param string|false|null $path 长连接响应路径, {string: 指定路径, false: 不指定路径, null: 响应请求路径}
     */
    public function response(mixed $data = false, string|false|null $path = null): void {
        $this->rendered = true;
        if (false === $data) {
            $data = $this->getAllAssignedStatus();
        }
        if ($this->rawRequest instanceof RawRequestHttp) {
            $this->rawRequest->response(is_string($data) ? $data : json_encode($this->getAllAssignedStatus(), JSON_UNESCAPED_UNICODE));
        } else if ($this->rawRequest instanceof RawRequestConnection) {
            $this->rawRequest->response($data, $path ?? $this->rawRequest->path);
        } else {
            $this->print($data);
        }
    }

    /**
     * 打印变量值到控制台
     * @param mixed $value
     * @param string $suffix
     */
    public function print(mixed $value, string $suffix = "\n"): void {
        printf('%s%s', is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), $suffix);
    }

    /**
     * 格式化并打印变量值到控制台
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

    /**
     * 从控制台取输入内容
     * @param string $label
     * @param int $size
     * @return string
     */
    public function input(string $label = '', int $size = 1024): string {
        echo $label;
        return rtrim(fgets(STDIN, $size), "\n\r");
    }

    /**
     * 渲染Api成功的结果
     * @param string|null $message
     * @param int|null $code
     */
    public function success(string|null $message = null, int|null $code = null): void {
        $this->assignStatus('status', true);
        $this->renderResult($message, $code);
    }

    /**
     * 渲染Api失败的结果
     * @param string|null $message
     * @param int|null $code
     */
    public function fail(string|null $message = null, int|null $code = null): void {
        $this->assignStatus('status', false);
        $this->renderResult($message, $code);
    }

    /**
     * 异常失败的结果
     * @param Throwable $throwable
     */
    public function exception(Throwable $throwable): void {
        $this->fail($throwable->getMessage(), $throwable->getCode());
    }

    /**
     * 渲染Api结果
     * @param string|null $message
     * @param int|null $code
     */
    private function renderResult(string|null $message, int|null $code): void {
        if (null !== $message) {
            $this->assignStatus('message', $message);
        }
        if (null !== $code) {
            $this->assignStatus('code', $code);
        }
        ! $this->rendererInstance instanceof TemplateRenderer ? $this->render()
            : $this->response(TemplateRenderer::renderPhp($this->request->project->config->template[$this->isHttpException ? 'exception' : 'status'], $this->statusData));
    }

    /**
     * 快捷响应Http异常
     * @param int $code
     * @param string $reason
     */
    public function httpException(int $code, string $reason = ''): void {
        static $statusMessageMapping = [
            '400' => 'Bad Request',
            '401' => 'Unauthorized',
            '403' => 'Forbidden',
            '404' => 'Not Found',
            '500' => 'Internal Server Error',
            '501' => 'Not Implemented',
            '502' => 'Bad Gateway',
            '503' => 'Service Unavailable',
        ];
        $this->rawRequest instanceof RawRequestHttp && $this->rawRequest->status($code, $reason ?: ($statusMessageMapping[$code] ?? ''));
        $this->isHttpException = true;
        $this->fail($reason, $code);
    }

    /**
     * 将变量指派到视图
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function assign(string $key, mixed $value): static {
        $this->statusData['data'][$key] = $value;
        return $this;
    }

    /**
     * 将变量批量指派到视图
     * @param array $mapping
     * @return $this
     */
    public function assignMapping(array $mapping): static {
        foreach ($mapping as $k=>$v) {
            $this->assign($k, $v);
        }
        return $this;
    }

    /**
     * 取指派的变量值
     * @param string $key
     * @return mixed
     */
    public function getAssigned(string $key): mixed {
        return $this->statusData['data'][$key] ?? null;
    }

    /**
     * 取指派的全部值
     * @return array
     */
    public function getAllAssigned(): array {
        return $this->statusData['data'] ?? [];
    }

    /**
     * 清除所有指派的值
     */
    public function clearAssigned(): void {
        unset($this->statusData['data']);
    }

    /**
     * 设置外层状态数据
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function assignStatus(string $key, mixed $value): static {
        $this->statusData[$key] = $value;
        return $this;
    }

    /**
     * 取全部状态数据
     * @return array
     */
    public function getAllAssignedStatus(): array {
        return $this->statusData;
    }
}