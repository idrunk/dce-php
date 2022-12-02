<?php
/**
 * Author: Drunk
 * Date: 2020-04-09 18:34
 */

namespace dce\project\request;

use dce\base\SwooleUtility;
use dce\base\Value;
use dce\config\DceConfig;
use dce\event\Event;
use dce\i18n\Locale;
use dce\log\LogManager;
use dce\project\Controller;
use dce\project\node\Node;
use dce\project\node\NodeManager;
use dce\project\Project;
use dce\project\ProjectManager;
use dce\project\session\Cookie;
use dce\project\session\Session;
use drunk\Structure;
use Swoole\Coroutine;
use Throwable;

class Request {
    public int $id;

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

    /** @var Locale 客户端本地化参数 */
    public Locale $locale;

    /** @var Controller 控制器，(用于全部类型请求) */
    public Controller $controller;

    /** @var string 请求地址，method + pathFormat */
    public string $location;

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
     * patch: get + patch
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

    /** @var array Http请求的patch参数 */
    public array $patch;

    /** @var array Http请求的上传文件集 */
    public array $files;

    /** @var int websocket与tcp连接的资源描述符 */
    public int $fd;

    /** @var array 供用户用的扩展属性 */
    private array $extends = [];

    public function __construct(RawRequest $rawRequest) {
        $this->id = RequestManager::currentId();
        $this->rawRequest = $rawRequest;
    }

    /**
     * 扩展属性
     * @param string $key
     * @param mixed|Value $value
     * @return mixed
     */
    public function ext(string $key, mixed $value = Value::Default): mixed {
        if ($value === Value::Default) {
            return $this->extends[$key] ?? Value::False;
        } else {
            return $this->extends[$key] = $value;
        }
    }

    /**
     * 路由定位并执行控制器
     * @throws RequestException
     * @throws Throwable
     */
    public function route(): void {
        Event::trigger(Event::BEFORE_ROUTE, $this->rawRequest);
        $this->node = NodeManager::tryReroute($this->rawRequest->routeGetNode());
        $this->location = "{$this->rawRequest->method}:{$this->node->pathFormat}";
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
        $this->projectHostValid();
        // 补充请求对象相关属性
        $this->rawRequest->supplementRequest($this);
        $this->locale = new Locale($this);
        LogManager::request($this);
        Event::trigger(Event::AFTER_ROUTE, $this);
        // 执行控制器方法
        $this->controller();
    }

    /**
     * 项目引导预处理, (每个项目仅在第一次进入时执行)
     */
    private function prepare(): void {
        $this->config->prepare && call_user_func_array($this->config->prepare, [$this]);
    }

    private function projectHostValid(): void {
        if (! ($this->project->getRootNode()->projectHosts ?? false)) return;
        // 若项目根节点绑定了主机，则当前节点有定义绑定的主机，或项目根节点与请求信息命中，才是合法请求
        foreach ($this->node->projectHosts ?? $this->project->getRootNode()->projectHosts as $nodeHost)
            if (Structure::arrayMatch($nodeHost, $this->rawRequest->getServerInfo())) return;
        throw new RequestException(RequestException::NO_NODE_ACCESS_PERMISSION);
    }

    /**
     * 解析并执行控制器方法
     * @throws RequestException
     * @throws Throwable
     */
    private function controller(): void {
        // 解析控制器
        $controller = explode('->', $this->node->controller ?? '');
        2 !== count($controller) && throw (new RequestException(RequestException::NODE_NO_CONTROLLER))->format($this->node->pathFormat);

        [$className, $method] = $controller;
        $class = "\\{$this->project->name}\\controller\\{$className}";
        ! is_subclass_of($class, Controller::class) && throw (new RequestException(RequestException::NODE_CONTROLLER_INVALID))->format($class);
        ! method_exists($class, $method) && throw (new RequestException(RequestException::CONTROLLER_METHOD_INVALID))->format($method);

        if (SwooleUtility::inSwoole()) {
            // 如果配置了开启协程, 且当前未在协程中, 则创建协程容器执行控制器 (在server的onRequest等回调中, 依靠server配置是否开启协程而不是node配置)
            if ($this->node->enableCoroutine && ! SwooleUtility::inCoroutine()) {
                Coroutine\run(fn() => $this->runController($class, $method, true));
                return;
            }
        } else if ($this->node->enableCoroutine) {
            throw new RequestException(RequestException::COROUTINE_NEED_SWOOLE);
        }
        $this->runController($class, $method);
    }

    /**
     * 执行控制器方法
     * @param string $class
     * @param string $method
     * @param bool $coroutineNode
     * @throws Throwable
     */
    private function runController(string $class, string $method, bool $coroutineNode = false): void {
        Event::trigger(Event::BEFORE_CONTROLLER, $this);
        $this->controller = new $class($this);
        Event::trigger(Event::ENTERING_CONTROLLER, $this->controller);
        $this->controller->call($method);
        Event::trigger(Event::AFTER_CONTROLLER, $this->controller);
        $coroutineNode && SwooleUtility::eventExit();
    }
}
