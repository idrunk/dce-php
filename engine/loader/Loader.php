<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 2:24
 */

namespace dce\loader;

use Closure;
use dce\event\Event;
use dce\project\Project;

class Loader {
    /**
     * @var string 类加载完成事件名
     * @callable($className)
     */
    public const EVENT_ON_CLASS_LOAD = 'EVENT_ON_CLASS_LOADED';

    /** @var array 自动加载名字空间映射表 */
    private static array $mapping = [];

    /** @var array 自动加载类映射表 */
    private static array $classMapping = [];

    /**
     * 注册自动加载, 核心类预加载初始化
     */
    public static function init(): void {
        spl_autoload_register(['self', 'autoload'], true, true);
        self::prepare('\drunk\*', DCE_ROOT . 'drunk/');
        self::prepare('\dce\*', DCE_ROOT . 'engine/');
        self::dirOnce(DCE_ROOT .'function/');
    }

    /**
     * 处理自动加载
     * @param string $className
     * @return bool|string
     */
    private static function autoload(string $className): string|bool {
        if (null !== $loader = self::autoloadClass($className)) {
            $loader && self::triggerOnLoad($className);
            return $loader;
        }
        $prefix = $className;
        // 自动加载规则路径下所有文件, 如用debug/*将加载全部debug名字空间开头的类, 如debug/output/CliDebug.php, debug/Debug.php等
        while (false !== $pos = strrpos($prefix, '\\')) {
            // 解决用一个根名字空间匹配所有子级类或空间的问题, 如上述示例, 拆为debug/output与CliDebug时, 虽然通配符命中了, 但因debug/CliDebug不存在, \
            // 导致匹配失败, 会继续递归的拆为debug与output/CliDebug, 通配符还是会命中类名, 且debug/output/CliDebug也存在了, 真正载入了子类
            $prefix = substr($className, 0, $pos + 1);
            $relativeClass = substr($className, $pos + 1);
            $mappedFile = self::autoloadFile($prefix, $relativeClass);
            if ($mappedFile) {
                self::triggerOnLoad($className);
                return $mappedFile;
            }
            $prefix = rtrim($prefix, '\\');
        }
        return false;
    }

    /**
     * 触发类加载事件
     * @param string $className
     */
    private static function triggerOnLoad(string $className): void {
        Event::trigger(self::EVENT_ON_CLASS_LOAD, $className);
    }

    /**
     * 处理类自动加载
     * @param string $className
     * @return bool|null
     */
    private static function autoloadClass(string $className): bool|null {
        if (key_exists($className, self::$classMapping)) {
            $classPath = self::$classMapping[$className];
            if ($classPath instanceof Closure) {
                call_user_func($classPath, $className);
            } else {
                self::once($classPath);
            }
            return class_exists($className);
        }
        return null;
    }

    /**
     * 自动加载文件
     * @param string $prefix
     * @param string $relativeClass
     * @return bool
     */
    private static function autoloadFile(string $prefix, string $relativeClass): bool {
        $className = $prefix . $relativeClass;
        foreach (self::$mapping as $pattern => $dirBases) {
            $pattern = str_replace('\\', '\\\\', $pattern);
            if (! fnmatch($pattern, $className)) {
                continue; // 模式匹配map中符合规则的库目录地址
            }
            foreach ($dirBases as $dirBase) {
                if ($dirBase instanceof Closure) {
                    if (! class_exists($className)) {
                        // 因为自动加载会尝试通配符下所有类, 而这对于用户自定义方法来说都是同一个方法, 没有意义, 所以我们可以判断当类已存在时无需再回调
                        call_user_func($dirBase, $className);
                    }
                } else {
                    $path = $dirBase . str_replace('\\', '/', $relativeClass) . '.php';
                    if (! self::once($path)) {
                        continue;
                    }
                }
                if (class_exists($className)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 预加载PHP名字空间类
     * @param string $namespaceWildcard
     * @param string|Closure $dirBase
     * @param bool $isPrepend // 是否前插
     */
    public static function prepare(string $namespaceWildcard, string|Closure $dirBase, bool $isPrepend = false): void {
        $namespaceWildcard = ltrim($namespaceWildcard, '\\');
        if (! $dirBase instanceof Closure) {
            $dirBase = rtrim($dirBase, '\\/') . '/';
        }
        if (! key_exists($namespaceWildcard, self::$mapping)) {
            self::$mapping[$namespaceWildcard] = [];
        }
        if (! in_array($dirBase, self::$mapping[$namespaceWildcard])) {
            if ($isPrepend) {
                array_unshift(self::$mapping[$namespaceWildcard], $dirBase);
            } else {
                array_push(self::$mapping[$namespaceWildcard], $dirBase);
            }
        }
    }

    /**
     * 预加载PHP类
     * @param string $className
     * @param string|Closure $classPath
     */
    public static function preload(string $className, string|Closure $classPath): void {
        $className = ltrim($className, '\\');
        if (! key_exists($className, self::$classMapping)) {
            self::$classMapping[$className] = $classPath;
        }
    }

    /**
     * 将目录下所有PHP文件一次性引入
     * @param string $dir
     * @return bool
     */
    public static function dirOnce(string $dir): bool {
        if (! file_exists($dir)) {
            return false;
        }
        $lists = glob($dir . '*.php') ?: [];
        foreach ($lists as $file) {
            self::once($file);
        }
        return !! $lists;
    }

    /**
     * 一次性引入PHP文件
     * @param string $path
     * @return bool
     */
    public static function once(string $path): bool {
        if (! file_exists($path)) {
            return false;
        }
        require_once($path);
        return true;
    }

    /**
     * 预加载公共模块
     */
    public static function prepareCommon(): void {
        // 公共模型预加载准备
        self::prepare('\controller\*', APP_COMMON . 'controller/');
        self::prepare('\model\*', APP_COMMON . 'model/');
        self::prepare('\service\*', APP_COMMON . 'service/');
        // 加载方法库
        self::dirOnce(APP_COMMON . 'function/');
        // 初始化类装饰器
        ClassDecoratorManager::bindDceClassLoad();
    }

    /**
     * 预加载项目模块
     * @param Project $project
     */
    public static function prepareProject(Project $project): void {
        self::prepare("\\{$project->name}\\controller\\*", "{$project->path}controller/", true); // 预加载控制器类
        self::prepare("\\{$project->name}\\model\\*", "{$project->path}model/", true); // 预加载模型类
        self::prepare("\\{$project->name}\\service\\*", "{$project->path}service/", true); // 预加载服务类
    }
}
