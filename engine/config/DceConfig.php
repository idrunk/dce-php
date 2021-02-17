<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 03:46
 */

namespace dce\config;

use Closure;
use dce\db\connector\DbConfig;
use dce\sharding\id_generator\DceIdGenerator;
use dce\sharding\middleware\ShardingConfig;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Class config 配置操作基础类
 */
class DceConfig extends Config {
    /** @var string 应用ID (用于多应用部署在同台机器时, 标识其依赖公共组件的用户身份) */
    public string $appId;

    /** @var Closure|null 引导回调 (可以在此做全局初始化工作, 如设置池通道, 设置数据库代理等) */
    public Closure|null $bootstrap = null;

    /** @var Closure|null 项目预备回调 (可以做项目初始化相关工作) */
    public Closure|null $prepare = null;

    /**
     * @var array|null 项目路径 (自定义项目路径, 如 ['/project1', '/other/projects/'])
     * <pre>
     * 若以斜杠结尾, 视为根目录, 将扫描其下全部目录作为自定义项目
     * 无斜杠, 则作为一个单独的自定义项目
     * </pre>
     */
    public array|null $projectPaths = null;

    /** @var bool 是否重写模式, 用于生成伪静态Url */
    public bool $rewriteMode = false;

    /** @var string jsonp请求时的回调方法键名 */
    public string $jsonpCallback = 'callback';

    /** @var string 并发锁类名 */
    public string $lockClass;

    /** @var DceIdGenerator Id生成器 */
    public DceIdGenerator $idGenerator;

    /** @var array 缓存配置 (无法对项目单独设置, 但开发者模式全局配置覆盖有效) */
    #[ArrayShape([
        'file' => [ 'dir' => 'string', 'template_dir' => 'string', ],
        'memcache' => [ 'host' => 'string', 'backup_on' => 'bool', ],
        'memcached' => [ 'host' => 'string', 'backup_on' => 'bool', ],
    ])]
    public array $cache = [
        'default' => 'file',
        'file' => [
            'dir' => APP_RUNTIME .'cache/', // 文件缓存目录
            'template_dir' => APP_RUNTIME . 'tpl/', // PHP模板文件缓存目录
        ],
//        'memcache' => [
//            'host' => '', // 缓存服务器
//            'port' => 0,
//            'backup_on' => false, // 是否备份
//        ],
//        'memcached' => [
//            'host' => '', // 缓存服务器
//            'port' => 0,
//            'backup_on' => false, // 是否备份
//        ],
    ];

    /** @var array Session配置 */
    #[ArrayShape([
        'name' => 'string',
        'auto_start' => 'bool',
        'ttl' => 'int',
        'root' => 'string|int',
        'class' => 'string',
    ])]
    public array $session = [
        'name' => 'dcesid', // Sid名
        'auto_start' => 0, // 是否自动启动
        'ttl' => 3600, // Session存活时间
        'root' => APP_RUNTIME . 'session/', // 文件型Session处理器根目录或RedisSession处理器库号
        'class' => '\dce\project\request\SessionFile', // Session处理器类名
    ];

    /** @var array|string[] 需内置Http服务忽略的请求路径 */
    public array $blockPaths = [
        '/favicon.ico',
    ];

    /**
     * @var array Redis配置
     * <pre>
     * [
     *    'host' => '127.0.0.1',
     *    'port' => 6379,
     *    'password' => 'password',
     *    'index' => 0, // 默认库号
     * ]
     * </pre>
     */
    public array $redis;

    /**
     * @var DbConfig Mysql配置 (用户配置为数组, DCE会自动将其实例化为对象)
     * <pre>
     * 下述为分库版配置示例, 若无需分库, 则仅配置default配置即可
     * [
     *     'default' => [ // 默认库配置, 用于简单代理的数据查询, 或者分库代理数据查询时的非分库数据查询 (当完全无分库需求时可以将此配置上移一层, 即直接'mysql'=>[...])
     *         'host' => '127.0.0.1', // 主机地址
     *         'db_user' => 'root', // 数据库用户名
     *         'db_password' => 'password', // 数据库密码
     *         'db_name' => 'default_db', // 库名
     *         'db_port' => 3306, // 数据库端口
     *         'max_connection' => 8, // 连接池容量
     *     ],
     *     '127.0.0.1' => [ // 分库1配置, 一个配置可以表示某个区间分库, 多个子配置表示该区间库有多个副本, 若有标志is_master则表示为主库, 若皆无标志则全部为主库 (default库亦适用)
     *        [
     *            'label' => '127.0.0.1:33061', // 标记别名
     *            'host' => '127.0.0.1',
     *            'db_user' => 'root',
     *            'db_password' => 'password',
     *            'db_name' => 'sharding_db',
     *            'db_port' => 33061,
     *            'is_master' => 1, // 是否主库
     *        ],
     *        [
     *            'label' => '127.0.0.1:33062',
     *            'host' => '127.0.0.1',
     *            'db_user' => 'root',
     *            'db_password' => 'password',
     *            'db_name' => 'sharding_db',
     *            'db_port' => 33062,
     *        ],
     *    ]
     * ]
     * </pre>
     */
    public DbConfig $mysql;

    /**
     * @var ShardingConfig 分库规则配置 (用户配置为数组, DCE会自动将其实例化为对象)
     * <pre>
     * [
     *    'member' => [ // 分库别名 (按member划分的分库配置)
     *        'db_type' => 'mysql', // 数据库类型
     *        'type' => 'modulo', // 分库类型 (按模型分库)
     *        'modulus' => 4, // 按模分库模数 (分库数量)
     *        'cross_update' => true, // 是否允许跨库更新 (如set mid=2 where mid=1, 源数据位于库1, 但mid为2的数据应该储于库2, 开启这个开关后将会自动以插入+删除的方式移动数据, 否则会抛出无法update的异常)
     *        'allow_joint' => true, // 是否允许联表查询 (连表查询仅能联合主表所在的库来查询, 此开关可以关闭或开启该特性支持来避免开发人员的错误用法)
     *        'table' => [ // 适用于该分库规则的表名
     *            'member' => [
     *                'id_column' => 'mid', // 未配置sharding_column时将以id_column作为分库字段
     *            ],
     *            'member_login' => [
     *                'id_column' => 'id', // 若配置了ID字段, 则将使用生成器生成ID, 若同时配置了sharding_column, 则该字段将作为ID的基因字段
     *                'sharding_column' => 'mid', // 若未配置ID字段, 则将不主动生成ID, 分库将仅以sharding_column字段划分
     *            ],
     *        ],
     *        'mapping' => [ // 分库与ID取模余数映射表, 标记取模值与路由库的映射关系
     *            '222:3306' => 0,
     *            '221:33065' => 1,
     *            '222:33065' => 2,
     *            '2:33068' => 3,
     *        ],
     *    ],
     *    'daily_log' => [
     *        'db_type' => 'mysql',
     *        'type' => 'range', // 分库类型 (区间型分库)
     *        'table' => [
     *           'member_login' => [
     *               'id_column' => 'id',
     *           ],
     *        ],
     *        'mapping' => [ // 分库与ID区间起始值映射表, 标记ID处于哪个区间及对应哪个库
     *            '222:3306' => 0,
     *            '221:33065' => 15000000,
     *            '222:33065' => 30000000,
     *            '2:33068' => 45000000,
     *        ],
     *    ],
     * ]
     * </pre>
     */
    public ShardingConfig $sharding;

    /**
     * @var array Mysql分库拓库配置
     * <pre>
     * [
     *    'volume_per_transfer' => 1000, // 每次向扩展表平均迁移量
     *    'mapping' => [ // 扩展分库规则映射表
     *        'member' => [
     *            '222:33065' => 1,
     *        ],
     *        'daily_log' => [
     *            'db1:2333' => 60000000,
     *        ],
     *    ],
     *    'database' => [ // 扩展分库集
     *        '222:33065' => [
     *            'label' => '127.0.0.1:33065',
     *            'host' => '127.0.0.1',
     *            'db_user' => 'root',
     *            'db_password' => 'password',
     *            'db_name' => 'sharding_db',
     *            'db_port' => 33065,
     *        ],
     *    ],
     * ]
     * </pre>
     */
    public array $shardingExtend;

    /**
     * @var array Rpc服务配置 (设置后在应用启动时, 将会自动连接Rpc服务, 并拦截处理Rpc请求方法)
     * <pre>
     * [
     *    [
     *        'hosts' => [ // 提供Rpc服务的服务器
     *            ['host' => RpcUtility::DEFAULT_TCP_HOST, 'port' => RpcUtility::DEFAULT_TCP_PORT],
     *        ],
     *        'wildcards' => [RpcUtility::DEFAULT_NAMESPACE_WILDCARD,] // 所需拦截处理的通配符名字空间
     *    ],
     * ]
     * </pre>
     */
    public array $rpcServers = [];

    /**
     * @var array 内置Websocket服务配置
     * <pre>
     * [
     *    'host' => '0.0.0.0',
     *    'port' => 20461,
     *    'service' => '\\websocket\\service\\WebsocketServer',
     * ]
     * </pre>
     */
    public array $websocket = [];

    /**
     * @var array 内置Http服务配置
     * <pre>
     * [
     *    'host' => '0.0.0.0',
     *    'port' => 20460,
     *    'service' => '\\http\\service\\HttpServer',
     * ]
     * </pre>
     */
    public array $http = [];

    /**
     * @var array 内置Tcp服务配置
     * <pre>
     * [
     *    'host' => '0.0.0.0',
     *    'port' => 20462,
     *    'mode' => SWOOLE_PROCESS,
     *    'sock_type' => SWOOLE_SOCK_TCP,
     *    'service' => '\\tcp\\service\\TcpServer',
     *    'extra_ports' => [
     *        ['host' => '0.0.0.0', 'port' => 20463, 'sock_type' => SWOOLE_SOCK_UDP], // 同时监听20463端口的Udp服务
     *    ],
     * ]
     * </pre>
     */
    public array $tcp = [];

    /** @var array Swoole\Websocket\Server原生配置 */
    public array $swooleWebsocket = [];

    /** @var array Swoole\Http\Server原生配置 */
    public array $swooleHttp = [];

    /** @var array Swoole\Tcp\Server原生配置 */
    public array $swooleTcp = [];

    /** @var array 该配置将在引导时自动遍历作为参数给ini_set()调用 */
    public array $iniSet = [
        'date.timezone' => 'PRC',
    ];

    /** @var array Debug配置  */
    public array $debug = [
        'file_root' => APP_RUNTIME . 'log/debug/', // 文件储存根目录
        'url_root' => 'https://logger.drunkce.com/debug/', // HTTP储存根地址
    ];

    /** @var array 日志记录器配置 */
    public array $log = [
        'db' => [ // 数据库日志
            'console' => false, // 是否在控制台输出日志
        ],
    ];

    /** @inheritDoc */
    public function __construct($config) {
        $this->appId = hash('crc32', APP_ROOT);
        parent::__construct($config);
    }
}
