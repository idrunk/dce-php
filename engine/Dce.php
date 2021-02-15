<?php
/**
 * Author: Drunk
 * Date: 2016-11-12 2:50
 */

namespace dce;

use dce\base\Exception;
use dce\base\Lock;
use dce\cache\CacheManager;
use dce\config\ConfigManager;
use dce\config\DceConfig;
use dce\event\Event;
use dce\log\LogManager;
use dce\project\node\NodeManager;
use dce\project\ProjectManager;
use dce\project\request\RawRequestHttpCgi;
use dce\project\request\RawRequestCli;
use dce\project\request\Request;

final class Dce {
    private const DEV_PROJECT_NAME = 'developer';

    /** @var bool 是否开发环境 */
    private static bool $isDevEnvBool;

    /** @var int 启动状态: {0: 未初始化, 1: 仅初始化Dce, 2: 初始化了Dce及项目节点, 3: 已初始化并引导} */
    public static int $initState = 0;

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
        // PHP初始化
        self::phpInit();
        // 初始化日志类
        LogManager::init();
        // 拦截异常
        Exception::init();
        // 缓存初始化
        self::$cache = CacheManager::init();
        // 并发锁初始化
        self::$lock = Lock::init();
    }

    /** PHP环境初始化 */
    private static function phpInit(): void {
        foreach (self::$config->iniSet as $k => $v) {
            ini_set($k, $v);
        }
    }

    /**
     * 取应用ID
     */
    public static function getId(): string {
        static $id;
        if (null === $id) {
            $id = self::$config->appId ?? 0;
        }
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
        if (self::$initState > 0) {
            return;
        }
        self::$initState = 1;

        self::prepare();
        // 事件中可能会派生新进程，在此前需将环境初始化好
        Event::trigger(Event::AFTER_DCE_INIT);
    }

    /** 初始化Dce及项目节点 */
    public static function scan(): void {
        if (self::$initState > 0) {
            return;
        }
        self::$initState = 2;

        self::prepare();
        // 扫描并加载项目
        ProjectManager::scanLoad();
        self::$isDevEnvBool = !! ProjectManager::get(self::DEV_PROJECT_NAME);
        if (self::$config->bootstrap) {
            call_user_func(self::$config->bootstrap); // 执行用户自定义引导程序
        }
        // 扫描并初始化节点集
        NodeManager::scanInit();
        // 事件中可能会派生新进程，在此前需将环境初始化好
        Event::trigger(Event::AFTER_DCE_INIT);
    }

    /**
     * 引导路由
     * @throws project\request\RequestException
     */
    public static function boot(): void {
        self::scan();
        if (2 !== self::$initState) {
            return;
        }
        self::$initState = 3;

        if (DCE_CLI_MODE) {
            $rawRequest = new RawRequestCli();
        } else {
            $rawRequest = new RawRequestHttpCgi();
        }
        $rawRequest->init();
        $request = new Request($rawRequest);
        $request->route();
    }
}
