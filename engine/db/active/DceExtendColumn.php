<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2022-06-23 21:58
 */

namespace dce\db\active;

use dce\base\ExternalPropertyId;
use dce\db\entity\DbField;
use dce\db\entity\FieldType;
use dce\model\Property;

/**
 * @note ddl <pre>
 * create table dce_extend_column (
 *   table_id             tinyint unsigned not null,
 *   primary_id           bigint unsigned not null,
 *   column_id            tinyint unsigned not null,
 *   value                varbinary(255) not null,
 *   primary key (table_id, primary_id, column_id)
 * );
 * </pre>
 */
class DceExtendColumn extends DceExternalRecord {
    #[Property(id: ExternalPropertyId::COLUMN_ID), DbField(FieldType::Smallint, primary: true)]
    public int $columnId;
    #[Property(id: ExternalPropertyId::VALUE), DbField(FieldType::Varchar, 255)]
    public string $value;

    public static function getColumnProperty(): Property {
        return static::getPropertyById(ExternalPropertyId::ColumnId->value);
    }
    public static function getValueProperty(): Property {
        return static::getPropertyById(ExternalPropertyId::Value->value);
    }
}