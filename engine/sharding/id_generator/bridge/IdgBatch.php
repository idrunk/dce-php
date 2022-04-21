<?php
/**
 * Author: Drunk (idrunk.net drunkce.com)
 * Date: 2020-12-21 18:26
 */

namespace dce\sharding\id_generator\bridge;

use dce\base\TraitModel;

class IdgBatch {
    use TraitModel;

    public const TYPE_INCREMENT = 'increment';
    public const TYPE_TIME = 'time';

    // ************* 需配置属性 *************

    /** @var string ID类型, {increment, time} */
    public string $type;

    /** @var int 服务ID位宽 */
    public int $serverBitWidth;

    /** @var int 基因模数位宽 (如按用户名分库时, 可将用户名转为number, 截取装入指定位宽ID. 而按模分库时将以ID与分库数取模得到目标分库号.) */
    public int $moduloBitWidth;

    /** @var int 批次池位宽 (仅用于time型ID, increment型ID批次池不限位宽) */
    public int $batchBitWidth;

    /** @var int 客户端单批申请ID数 */
    public int $batchCount;

    /** @var int 服务ID */
    public int $serverId;


    // ************* 下述属性自动计算, 无需配置 *************

    /** @var int 客户端批次起始ID */
    public int $batchFrom;

    /** @var int 客户端批次截止ID */
    public int $batchTo;

    /** @var int 批次ID */
    public int $batchId;

    /** @var int 时间ID (仅用于time型ID) */
    public int $timeId;

    /** @var int 批次申请时间 */
    public int $batchApplyTime;

    /** @var int 基因模数 (截取自转为数字的基因分库字段, 主用于解析时存值) */
    public int $moduloId;
}