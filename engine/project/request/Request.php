<?php
/**
 * Author: Drunk
 * Date: 2020-04-09 18:34
 */

namespace dce\project\request;

use dce\base\SwooleUtility;
use dce\config\DceConfig;
use dce\event\Event;
use dce\project\node\Node;
use dce\project\node\NodeManager;
use dce\project\Project;
use dce\project\ProjectManager;
use dce\project\view\View;
use dce\project\view\ViewHttp;
use dce\rpc\DceRpcClient;
use Swoole\Coroutine;

class Request {
    /** @var RawRequest 原始请求对象, (用于全部类型请求) */
    public RawRequest $rawRequest;

    /** @var Node 当前请求的节点对象, (用于全部类型请求) */
    public Node $node;

    /** @var Url 当前节点Url对象, (用于Http类型请求) */
    public Url $url;

    /** @var Project 当前项目对象, (用于全部类型请求) */
    public Project $project;

    /** @var DceConfig 当前项目配置对象, (用于全部类型请求) */
    public DceConfig $config;

    /** @var Cookie 当前项目Cookie对象, (用于Http类型请求) */
    public Cookie $cookie;

    /** @var Session 当前项目Session对象, (用于全部类型请求) */
    public Session $session;

    /**
     * @var mixed 原始请求数据, (用于全部类型请求)
     * <pre>
     * http: string php://input
     * cli: array argv除开脚本参数外的参数集
     * tcp: mixed 解包后除开路径外的数据
     * udp: 同tcp
     * websocket: 同tcp
     * </pre>
     */
    public mixed $rawData;

    /**
     * @var array 请求参数
     * <pre>
     * get: get参数
     * post: get + post
     * put: get + put
     * delete: get + delete
     * cli: cli
     * tcp: 除开路径的数据若为json，则此处为数组，否则为null（可以取rawData）
     * udp: 同tcp
     * websocket: 同tcp
     * </pre>
     */
    public array $request;

    /** @var array 不带前缀的cli参数, 如['h'=>'', 'p'=>''] */
    public array $pureCli;

    /** @var array 完整cli参数, 如['-h'=>'', '--p'=>''] */
    public array $cli;

    /** @var array Http请求的get参数 */
    public array $get;

    /** @var array Http请求的post参数 */
    public array $post;

    /** @var array Http请求的put参数 */
    public array $put;

    /** @var array Http请求的delete参数 */
    public array $delete;

    /** @var array Http请求的上传文件集 */
    public array $files;

    /** @var int websocket与tcp连接的资源描述符 */
    public int $fd;

    /** @var array 供用户用的扩展属性 */
    public array $ext = [];

    public function __construct(RawRequest $rawRequest) {
        $this->rawRequest = $rawRequest;
    }

    /**
     * 扩展属性
     * @param string $key
     * @param mixed $value
     */
    public function extend(string $key, mixed $value): void {
        $this->ext[$key] = $value;
    }

    /**
     * 路由定位并执行控制器
     * @throws RequestException
     */
    public function route() {
        Event::trigger(Event::BEFORE_REQUEST, [$this->rawRequest]);
        $this->node = $this->rawRequest->routeGetNode();
        // 当前项目赋值
        $this->project = ProjectManager::get($this->node->projectName);
        $this->config = $this->project->getConfig();
        // 不重复完善项目属性
        if (! $this->project->isComplete) {
            // 当前项目配置赋值
            $this->project->getPureConfig();
            // 当前项目补全节点集信息
            $this->project->setNodeTree(NodeManager::getTreeByPath($this->project->name));
            // 项目引导预处理
            $this->prepare();
            // 标记project已完善
            $this->project->isComplete = true;
        }
        // 补充请求对象相关属性
        $this->rawRequest->supplementRequest($this);
        // 执行控制器方法
        $this->controller();
    }

    /**
     * 项目引导预处理, (每个项目仅在第一次进入时执行)
     * @throws RequestException
     */
    private function prepare(): void {
        // 准备配置的Rpc服务
        DceRpcClient::prepare($this->project);
        if ($this->config->prepare) {
            call_user_func_array($this->config->prepare, [$this]);
        }
    }

    /**
     * 解析并执行控制器方法
     * @throws RequestException
     */
    private function controller (): void {
        // 解析控制器
        $controller = explode('->', $this->node->controller ?? '');
        if (2 !== count($controller)) {
            throw new RequestException("节点 {$this->node->pathFormat} 未配置控制器或配置错误");
        }
        [$className, $method] = $controller;
        $class = "\\{$this->project->name}\\controller\\{$className}";
        if (! is_subclass_of($class, View::class)) {
            throw new RequestException("控制器 {$class} 异常或不存在, 需继承View子类");
        }
        if (! method_exists($class, $method)) {
            throw new RequestException("控制器方法 {$method} 不存在");
        }
        if (SwooleUtility::inSwoole()) {
            if ($this->node->hookCoroutine) {
                SwooleUtility::coroutineHook();
            }
            // 如果配置了开启协程, 且当前未在协程中, 则创建协程容器执行控制器 (在server的onRequest等回调中, 依靠server配置是否开启协程而不是node配置)
            if ($this->node->enableCoroutine && ! SwooleUtility::inCoroutine()) {
                Coroutine\run(fn() => $this->runController($class, $method));
                return;
            }
        } else if ($this->node->hookCoroutine || $this->node->enableCoroutine) {
            throw new RequestException('未安装Swoole扩展, 无法开启协程');
        }
        $this->runController($class, $method);
    }

    /**
     * 执行控制器方法
     * @param string $class
     * @param string $method
     */
    private function runController(string $class, string $method): void {
        // 执行控制器
        Event::trigger(Event::BEFORE_CONTROLLER, [$this]);
        $controller = new $class($this);
        $controller->call($method);
        Event::trigger(Event::AFTER_RESPONSE, [$this, $controller]);
    }
}
