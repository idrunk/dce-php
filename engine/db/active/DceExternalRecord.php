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

abstract class DceExternalRecord extends DbActiveRecord {
    #[Property(id: ExternalPropertyId::TABLE_ID), DbField(FieldType::Smallint, primary: true)]
    public int $tableId;
    #[Property(id: ExternalPropertyId::PRIMARY_ID), DbField(FieldType::Bigint, primary: true)]
    public int $primaryId;

    protected static function getForeignKeyPropertyIds(): array {
        return [ExternalPropertyId::PrimaryId->value];
    }
    /** @return Property[] */
    public static function getForeignKeyProperties(): array {
        return array_map(fn($pid) => self::getPropertyById($pid), static::getForeignKeyPropertyIds());
    }
    public static function getForeignKeyPropertyByIndex(int $index): Property {
        return static::getPropertyById(static::getForeignKeyPropertyIds()[$index]);
    }
    public static function getForeignKeyValues(): array {
        return array_reduce(self::getForeignKeyProperties(), fn($map, $p) => $map + [$p->field->getName() => $this->{$p->name}], []);
    }
    public static function getTableProperty(): Property {
        return static::getPropertyById(ExternalPropertyId::TableId->value);
    }
}