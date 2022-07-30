<?php
/**
 * Author: Drunk
 * Date: 2021/12/01 01:18
 */

namespace dce\base;

use dce\Dce;

enum Value {
    case Default;
    case False;
}

enum DceInit {
    case Pending; // 未初始化
    case Minimal; // 仅初始化Dce
    case Scan; // 初始化了Dce及项目节点
    case Boot; // 已初始化并引导
}

// for Model
enum CoverType {
    case Unset; // unset default
    case Replace; // replace value
    case Ignore; // ignore existed
}

// for Model
enum ExtractType {
    case KeepKey;
    case DbKey;
    case DbSave; // += DbKey + Serialize (no extend columns)
    case ExtSave; // += DbKey + Serialize (only extend columns)
//    case DbExtSave; // += DbKey + Serialize (with extend columns)
    case Serialize; // += DbKey
}

// for Model property
enum StorableType {
    case Array;
    case BackedEnum;
    case Scalar;
    case Serializable;
    case Unable;
}

// for Tree
enum TreeTraverResult {
    case StopChild; // 停止遍历子节点
    case StopSibling; // 停止遍历兄弟节点
    case StopAll; // 停止遍历全部
}

// for Parser
enum ParserTraverResult {
    case Exception; // 需抛异常
    case Return; // 需返回
    case Break; // 需退出循环
    case Continue; // 需跳过循环轮
    case Step; //
}

enum FindMethod {
    case Main; // 仅找主记录
    case MainCache; // 尝试从缓存找主记录
    case MainCacheDeep; // 从缓存找主记录，若未找到则从数据库查找
    case Extend; // 仅找扩展记录
    case ExtendCache; // 尝试从缓存找扩展记录
    case ExtendCacheDeep; // 从缓存找扩展记录，若未找到则从数据库查找
    case Both; // 查找主扩记录
    case BothCache; // 尝试从缓存找主扩记录
    case BothCacheDeep; // 从缓存找主扩记录，若未找到则从数据库查找
}

enum SaveMethod {
    case Main; // 仅存主字段
    case Extend; // 仅存扩展字段
    case ExtendClean; // 仅存扩展字段并清除之外的
    case Both; // 存全部主扩字段
    case BothClean; // 存全部主扩字段并清除之外的扩展字段
}

enum PropertyFlag: int {
    case ExtendColumn = 1; // 扩展字段
    case Cache = 2; // 缓存
    case NoCache = 4; // 不缓存（优先级高于Cache，若两者都未标记，则将被model级缓存设置覆盖）
    case ModifyRecord = 8; // 记录修改记录
}

// for Dce external model column property
enum ExternalPropertyId: int {
    case TableId = 1;
    case PrimaryId = 2;
    case SecondaryId = 3;
    case ColumnId = 4;
    case Value = 5;
    case Version = 10;
    case OriginalRecord = 11;

    public const TABLE_ID = 1;
    public const PRIMARY_ID = 2;
    public const SECONDARY_ID = 3;
    public const COLUMN_ID = 4;
    public const VALUE = 5;
    public const VERSION = 10;
    public const ORIGINAL_RECORD = 11;
}

enum LogMethod: string {
    case Append = 'append';
    case Replace = 'replace';
    case Prepend = 'prepend';
}

enum LoggerType: int {
    case Dce = 0;
    case Exception = 1;
    case Request = 2;
    case Response = 3;
    case Connect = 4;
    case Send = 5;
    case RpcConnect = 6;
    case RpcRequest = 7;
    case RpcResponse = 8;
    case Cron = 9;
    case CronDone = 10;
    case QueryRequest = 11;
    case QueryResponse = 12;

    /**
     * 记录配置
     * @return array{console: bool, logfile_power: bool, logfile: string, logfile_format: string}
     */
    public function config(): array {
        static $mapping = [];
        ! key_exists($this->name, $mapping) && ($c = Dce::$config->log) && $mapping[$this->name] = match ($this) {
            self::Connect => array_merge($c['access'], ['console' => $c['access']['connect']]),
            self::Request => array_merge($c['access'], ['console' => $c['access']['request']]),
            self::Response => array_merge($c['access'], ['console' => $c['access']['response']]),
            self::Send => array_merge($c['access'], ['console' => $c['access']['send']]),
            self::RpcConnect => array_merge($c['rpc'], ['console' => $c['rpc']['connect']]),
            self::RpcRequest => array_merge($c['rpc'], ['console' => $c['rpc']['request']]),
            self::RpcResponse => array_merge($c['rpc'], ['console' => $c['rpc']['response']]),
            self::CronDone => $c['cron'],
            self::QueryRequest, self::QueryResponse => $c['db'],
            default => $c[strtolower($this->name)],
        };
        return $mapping[$this->name];
    }

    /**
     * 记录缓冲规格
     * @return array{delay: int, timeout: int, capital: int}
     */
    public function spec(): array {
        return match($this->name) {
            default => [
                'delay' => 100,
                'timeout' => 500,
                'capital' => 1024,
            ],
        };
    }
}
