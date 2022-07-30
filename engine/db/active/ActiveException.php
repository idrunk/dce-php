<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 10:26
 */

namespace dce\db\active;

use dce\i18n\Language;
use dce\model\ModelException;

// 1400-1439
class ActiveException extends ModelException {
    #[Language(['%s 必须继承自 %s'])]
    public const RELATION_ACTIVE_RECORD_INVALID = 1400;

    #[Language(['当前对象尚未保存，无法删除'])]
    public const CANNOT_DELETE_BEFORE_SAVE = 1401;

    #[Language(['当前对象尚未保存，无法更新'])]
    public const CANNOT_UPDATE_BEFORE_SAVE = 1402;

    #[Language(['不支持按 with 方式的 each 操作'])]
    public const EACH_NO_SUPPORT_WITH = 1410;

    #[Language(['关系名 %s 无效'])]
    public const RELATION_NAME_INVALID = 1411;

    #[Language(['下标型关系名 %s 无效'])]
    public const RELATION_KEY_INVALID = 1412;

    #[Language(['主活动记录中缺少与 %s 关系数据关联的字段 %s'])]
    public const PRIMARY_RECORD_NO_FOREIGN_COLUMN = 1413;

    #[Language(['未设置有效的映射关系'])]
    public const NO_RELATION_MAPPING = 1420;

    #[Language(['当前模型未包含 %s 属性'])]
    public const RELATION_MODEL_HAVE_NO_FOREIGN = 1421;

    #[Language(['未定义主键或定义过多主键，不适配当前扩展模型'])]
    public const NO_PK_OR_TOO_MANY_PKS = 1425;

    #[Language(['该模型未定义扩展属性'])]
    public const NO_EXT_PROPERTY_DEFINED = 1426;

    #[Language(['活动记录find必须传入标量或主键值表条件'])]
    public const FIND_WHERE_MUST_BE_SCALAR_OR_MAPPING = 1429;
}
