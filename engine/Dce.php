<?php
/**
 * Author: Drunk
 * Date: 2016-11-12 2:50
 */

namespace dce;

use dce\base\DceInit;
use dce\base\Exception;
use dce\base\Lock;
use dce\base\SwooleUtility;
use dce\cache\CacheManager;
use dce\config\ConfigManager;
use dce\config\DceConfig;
use dce\event\Daemon;
use dce\event\Event;
use dce\i18n\Language;
use dce\loader\Loader;
use dce\log\LogManager;
use dce\project\node\NodeManager;
use dce\project\ProjectManager;
use dce\project\request\RawRequestHttpCgi;
use dce\project\request\RawRequestCli;
use dce\project\request\RequestManager;
use drunk\Structure;

final class Dce {
    private const DEV_PROJECT_NAME = 'developer';

    /** @var bool 是否开发环境 */
    private static bool $isDevEnvBool;

    /** @var DceInit 启动状态 */
    public static DceInit $initState = DceInit::Pending;

    /** @var DceConfig DCE全局配置 */
    public static DceConfig $config;

    /** @var CacheManager 缓存对象实例 */
    public static CacheManager $cache;

    /** @var Lock 并发锁实例 */
    public static Lock $lock;

    /**
     * 走你
     * @throws null
     */
    private static function prepare(): void {
        // 预加载公共类库
        Loader::prepareCommon();
        // 加载公共配置
        self::$config = ConfigManager::getCommonConfig();
        // 缓存初始化
        self::$cache = CacheManager::init();
        LogManager::dce(new Language(['正在加载Dce类库...', 'DCE library loading...']));
        // PHP初始化
        self::phpInit();
        // 拦截异常
        Exception::init();
        // 并发锁初始化
        self::$lock = Lock::init();
    }

    /** PHP环境初始化 */
    private static function phpInit(): void {
        Structure::forEach(self::$config->iniSet, fn($v, $k) => ini_set($k, $v));
        SwooleUtility::coroutineSet(self::$config->coroutineSet);
    }

    /**
     * 取应用ID
     */
    public static function getId(): string {
        static $id;
        null === $id && $id = self::$config->app['id'] ?? 0;
        return $id;
    }

    /**
     * 是否开发环境
     * @return bool
     */
    public static function isDevEnv(): bool {
        return self::$isDevEnvBool;
    }

    /** 仅初始化Dce */
    public static function initOnly(): void {
        if (self::$initState !== DceInit::Pending) return;
        self::$initState = DceInit::Minimal;

        self::prepare();
        // 事件中可能会派生新进程，在此前需将环境初始化好
        Event::trigger(Event::AFTER_DCE_INIT);
    }

    /** 初始化Dce及项目节点 */
    public static function scan(): void {
        if (self::$initState !== DceInit::Pending) return;
        self::$initState = DceInit::Scan;

        self::prepare();
        // 扫描并加载项目
        ProjectManager::scanLoad();
        self::$isDevEnvBool = !! ProjectManager::get(self::DEV_PROJECT_NAME);
        self::bootstrap();
        // 扫描并初始化节点集
        NodeManager::scanInit();
        // 事件中可能会派生新进程，在此前需将环境初始化好
        Event::trigger(Event::AFTER_DCE_INIT);
    }

    private static function bootstrap(): void {
        self::$config->bootstrap && call_user_func(self::$config->bootstrap); // 执行用户自定义引导程序
        Event::one(Event::AFTER_ROUTE, [Daemon::class, 'tryAutoDaemon']);
    }

    /** 引导路由 */
    public static function boot(): void {
        self::scan();
        if (self::$initState !== DceInit::Scan) return;
        self::$initState = DceInit::Boot;

        LogManager::dce(new Language(['正在引导请求处理器...', 'Request handler is booting...']));
        Exception::catchRequest([RequestManager::class, 'route'], DCE_CLI_MODE ? RawRequestCli::class : RawRequestHttpCgi::class);
    }
}
