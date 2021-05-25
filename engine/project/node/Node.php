<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/13 23:40
 */

namespace dce\project\node;

use Attribute;
use dce\base\TraitModel;
use drunk\Char;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Node {
    use TraitModel {
        TraitModel::arrayify as baseArrayify;
    }

    /** @var string 节点ID, 若未在配置中指定, 则会自动生成 */
    public string $id;

    /** @var string 节点名 */
    public string $name;

    /** @var string 节点路径名 */
    public string $pathName;

    /** @var string[] 请求类型, [get, post, put, delete, cli], {会被继承} */
    public array $methods;

    /** @var string 节点路径, 如news */
    public string $path;

    /** @var string 格式化的路径, 如补全了项目部分, home/news */
    public string $pathFormat;

    /** @var string 节点所属项目名 */
    public string $projectName;

    /** @var string 映射的控制器方法, 如NewsController::detail, 若在子目录news中, 则应定义名字空间news, 此时该配置应为news\\NewsController::detail */
    public string $controller;

    /** @var bool 是否开启协程容器, 默认为否, 若在Swoole Server环境, 则是通过Server配置控制, 不受Node属性影响, {会被继承} */
    public bool $enableCoroutine;

    /** @var bool 是否协程化IO操作, 当$enableCoroutine=true时此参数将默认为true, {会被继承} */
    public bool $hookCoroutine;

    /** @var string 渲染方式, json/xml/jsonp, 或PHP模板文件路径, 相对于项目模板根目录(project/home/template/)路径, 如news/detail.php */
    public string $render;

    /** @var string 模板布局, 相对于项目模板根目录路径, 如layout/default.php, 留空则不使用布局, {会被继承} */
    public string $templateLayout;

    /** @var int 渲染缓存 {0: 不缓存, 1: 缓存Api数据, 2: 缓存模板, 4: 缓存渲染页面} */
    public int $renderCache = 0;

    /** @var bool 可省略的节点路径, 如home节点配置为真, 则home/news节点的url可简化为news */
    public bool $omissiblePath = false;

    /** @var NodeArgument[] 参数配置集 */
    public array $urlArguments = [];

    /** @var bool 参数位未传时是否保留分隔符, 如保留时为news/1---, 不保留时则为news/1 */
    public bool $urlPlaceholder = true;

    /** @var array 允许的后缀, 下述配置情况下news或news/都能匹配到对应节点 */
    public array $urlSuffix = ['', '/'];

    /** @var string 301永久转移目标地址 */
    public string $http301;

    /** @var string 302临时跳转目标地址 */
    public string $http302;

    /** @var string Jsonp请求时的回调方法url参数名 */
    public string $jsonpCallback = 'callback';

    /** @var bool 是否惰性匹配, (匹配到此及命中, 不再继续匹配剩余路径) */
    public bool $lazyMatch = false;

    /** @var bool 是否自动捕获抛出异常 */
    public bool $autoCatch = true;

    /** @var array 允许跨域的主机, 若配置了, 则自动允许所配的主机访问 {会被继承} */
    public array $corsOrigins;

    /** @var bool 是否目录节点 */
    public bool $isDir = false;

    /** @var array 项目绑定域名 (只在项目根节点配置有效, 当访问绑定的域名时, 会自动路由到相应项目下) */
    public array $projectHosts;

    /** @var array 用户自定义参数 */
    public array $extra;

    /**
     * 节点类. 本类同时作为节点实体类与注解类, 仅作为实体类时才会被实例化, 作为注解类时仅用来提供IDE智能提示
     * @throws NodeException
     */
    public function __construct(
        array|string|null $path = null, // 作为实体类时为节点属性表的数组
        string|array|null $methods = null, // 作为实体类时为项目名的字符串
        string|null $id = null,
        string|null $name = null,
        bool|null $enableCoroutine = null,
        bool|null $hookCoroutine = null,
        string|null $render = null,
        string|null $templateLayout = null,
        array|null $corsOrigins = null,
        array|null $projectHosts = null,
        int|null $apiCache = null,
        bool|null $omissiblePath = null,
        array|null $urlArguments = null,
        bool|null $urlPlaceholder = null,
        array|null $urlSuffix = null,
        bool|null $lazyMatch = null,
        string|null $http301 = null,
        string|null $http302 = null,
        string|null $jsonpCallback = null,
        bool|null $autoCatch = null,
        array|null $extra = null,
        bool $controllerPath = false, // 是否为控制器根路径
    ) {
        if (is_array($path) && is_string($methods)) {
            $this->setProperties($this->init($path, $methods));
            $this->projectName = $methods;
            ['path_name' => $this->pathName] = $this->genPathInfo();
        }
    }

    /**
     * 初始化补充节点属性
     * @param array $properties
     * @param string $projectName
     * @return array
     * @throws NodeException
     */
    private function init(array $properties, string $projectName): array {
        if (! isset($properties['path'])) {
            throw new NodeException(NodeException::NODE_PATH_MISSION);
        }
        $idGene = $properties['path_format'] = $properties['path'];
        if (0 !== stripos($idGene, $projectName)) {
            // 如果path缺少project部分, 则自动补上
            $idGene = $properties['path_format'] = "{$projectName}/{$idGene}";
        }
        if (isset($properties['methods'])) {
            if (! is_array($properties['methods'])) {
                $properties['methods'] = [$properties['methods']];
            }
            array_walk($properties['methods'], function (& $method) {
                if (! is_string($method)) {
                    throw new NodeException(NodeException::NODE_METHODS_NEED_ARRAY);
                }
                // 若有指定请求类型, 则小写处理, 否则为开放式(即url匹配到即可, 不对请求类型作路由匹配)
                $method = strtolower($method);
            });
        }
        if (isset($properties['url_arguments'])) {
            // 初始化参数
            $properties['url_arguments'] = self::initArguments($properties['url_arguments']);
            $argument_keys = array_column($properties['url_arguments'], 'name');
            asort($argument_keys);
            $idGene .= ';'.implode('-', $argument_keys);
        }
        if (isset($properties['url_suffix']) && is_string($properties['url_suffix'])) {
            // 如果配置的后缀为单个的字符串, 则将其转为数组
            $properties['url_suffix'] = [$properties['url_suffix']];
        }
        if (! isset($properties['id'])) {
            // 如果没有自定义节点ID, 则生成
            $properties['id'] = (string) hexdec(hash('crc32', $idGene)); // generate hash id for node
        }
        return $properties;
    }

    /**
     * 解析path取组成部件
     */
    public function genPathInfo(): array {
        preg_match('/^(?:((?:(\w+)\/)?.+?)\/)?(\w+)$/', $this->pathFormat, $matches);
        [, $pathParent, $projectName, $nodeName] = $matches;
        return [
            'project_name' => $projectName ?: $nodeName,
            'path_name' => $nodeName,
            'path_format' => $this->pathFormat,
            'path_parent' => $pathParent,
        ];
    }

    /**
     * 补全Node Url参数配置属性
     * @param array $arguments
     * @return array
     */
    private static function initArguments (array $arguments): array {
        foreach( $arguments as $j => $argument) {
            $arguments[$j] = new NodeArgument($argument);
        }
        return $arguments;
    }

    /**
     * 数组化当前节点
     * @return array
     */
    public function arrayify(): array {
        $properties = $this->baseArrayify();
        $properties['urlArguments'] = $this->urlArgumentsArrayify();
        if (isset($this->extra)) {
            $properties['extra'] = $this->extra;
        }
        return $properties;
    }

    /**
     * 数组化当前节点url参数集
     * @return array
     */
    private function urlArgumentsArrayify(): array {
        $children = [];
        foreach ($this->urlArguments as $child) {
            $children[] = $child->arrayify();
        };
        return $children;
    }

    /**
     * 取构造函数参数列表
     * @return array
     */
    private static function constructArgs(): array {
        static $args;
        if (null === $args) {
            $args = array_column((new ReflectionClass(self::class))->getConstructor()->getParameters(), 'name');
        }
        return $args;
    }

    /**
     * 将以反射的形式取到的AttrNode参数转化完善为有效Node配置
     * @param ReflectionMethod $method
     * @param ReflectionAttribute $attribute
     * @return array
     */
    public static function refToNodeArguments(ReflectionMethod $method, ReflectionAttribute $attribute): array {
        $arguments = $attribute->getArguments();
        foreach ($arguments as $k => $arg) {
            if (is_int($k)) {
                $arguments[self::constructArgs()[$k]] = $arg;
                unset($arguments[$k]);
            }
        }
        $class = $method->getDeclaringClass();
        // 不要将类魔术方法作为控制器方法
        if (! str_starts_with($methodName = $method->getName(), '__')) {
            $arguments['controller'] = preg_replace('/^.+?\bcontroller\\\\(.+)$/', "$1->{$methodName}", $class->getName());
        }
        return $arguments;
    }

    /**
     * 自动节点路径, 包括控制器及子目录路径 (给未指定路径的节点以控制器方法作为路径, 自动补上控制器及父目录路径)
     * @param array $node
     * @param string $controllerPath
     * @param string $projectName
     */
    public static function fillControllerNodePath(array & $node, string $controllerPath, string $projectName): void {
        $nodePath = $node['path'] ?? null;
        if (
            ! preg_match('/^((?:\w+\\\\)+)?\w+->(\w+)$/', $node['controller'] ?? '', $matches)
            || (! $matches[1] && $nodePath === $projectName)
        ) {
            // 如果当前节点为控制器节点, 或者为根目录下的项目同名节点, 则不作处理直接返回
            return;
        }
        [, $dir, $method] = $matches;
        $dir = str_replace('\\', '/', $dir);
        if ($controllerPath && $nodePath !== $controllerPath) {
            $dir .= "{$controllerPath}/";
        }
        $node['path'] = $dir . ($nodePath ?: Char::snakelike($method));
    }
}
