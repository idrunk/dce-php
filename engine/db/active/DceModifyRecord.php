<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2022-07-20 00:12
 */

namespace dce\db\active;

use dce\base\ExternalPropertyId;
use dce\db\entity\DbField;
use dce\db\entity\FieldType;
use dce\model\Property;

/**
 * @note ddl <pre>
 * create table dce_modify_record (
 *   table_id             tinyint unsigned not null,
 *   primary_id           bigint unsigned not null,
 *   version              smallint unsigned not null,
 *   original_record      json not null,
 *   primary key (table_id, primary_id, version)
 * );
 * </pre>
 */
class DceModifyRecord extends DceExternalRecord {
    #[Property(id: ExternalPropertyId::VERSION), DbField(FieldType::Smallint, primary: true)]
    public int $version;
    #[Property(id: ExternalPropertyId::ORIGINAL_RECORD), DbField(FieldType::Json)]
    public string $originalRecord;

    public static function getVersionProperty(): Property {
        return static::getPropertyById(ExternalPropertyId::Version->value);
    }
    public static function getOriginalRecordProperty(): Property {
        return static::getPropertyById(ExternalPropertyId::OriginalRecord->value);
    }
}