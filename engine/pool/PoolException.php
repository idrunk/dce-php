<?php
/**
 * Author: Drunk
 * Date: 2019-2-7 4:53
 */

namespace dce\pool;

use dce\base\Exception;
use dce\i18n\Language;

// 1600-1649
class PoolException extends Exception {
    // 脚本异常
    #[Language(['配置类无效'])]
    public const INVALID_CONFIG_CLASS = 1600;

    #[Language(['实例队列通道无效'])]
    public const INVALID_CHANNEL = 1601;

    // 运行时异常
    #[Language(['所传$config无法与池生产配置相匹配'])]
    public const CONFIG_PRODUCTION_NOT_MATCH = 1610;

    #[Language(['池中暂无空闲实例'])]
    public const CHANNEL_BUSYING = 1611;

    #[Language(['目标实例非当前有效池实例, 无法获取映射表'])]
    public const INVALID_CHANNEL_INSTANCE = 1612;


    #[Language(['连接异常断开，已重试 %s 次均未成功，重连失败'])]
    public const DISCONNECTED_RETRIES_OVERFLOWED = 1630;

    #[Language(['连接异常断开，事务中已有成功的请求，无法重试'])]
    public const DISCONNECTED_TRANSACTION_ACTIVATED = 1631;


    #[Language(['生成连接实例失败, 无法连接到Redis服务'])]
    public const CONNECT_REDIS_FAILED = 1640;


    #[Language(['%s 连接异常断开，将尝试重连'])]
    public const WARNING_RETRY_CONNECT = 1649;
}
