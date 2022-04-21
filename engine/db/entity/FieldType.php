<?php
/**
 * Author: Drunk
 * Date: 2019/9/3 14:08
 */

namespace dce\db\entity;

enum FieldType: string {
    case Int = 'INT';
    case Tinyint = 'TINYINT';
    case Smallint = 'SMALLINT';
    case Mediumint = 'MEDIUMINT';
    case Bigint = 'BIGINT';
    case Decimal = 'DECIMAL';
    case Float = 'FLOAT';
    case Double = 'DOUBLE';

    case Varchar = 'VARCHAR';
    case Char = 'CHAR';
    case Tinytext = 'TINYTEXT';
    case Text = 'TEXT';
    case Mediumtext = 'MEDIUMTEXT';
    case Longtext = 'LONGTEXT';
    case Tinyblob = 'TINYBLOB';
    case Blob = 'BLOB';
    case Mediumblob = 'MEDIUMBLOB';
    case Longblob = 'LONGBLOB';
    case Json = 'JSON';

    case Date = 'DATE';
    case Datetime = 'DATETIME';
    case Timestamp = 'TIMESTAMP';
    case Time = 'TIME';
    case Year = 'YEAR';

    public function getLength(int $length, bool $isUnsigned): int {
        return $length > 0 ? $length : (match ($this) {
            self::Int => [10, 11],
            self::Tinyint => [3, 4],
            self::Smallint => [5, 6],
            self::Mediumint => [8, 8],
            self::Bigint => [20, 20],
            default => [0, 0],
        })[(int) ! $isUnsigned];
    }

    public function isNumeric(): bool {
        return in_array($this, [self::Int, self::Tinyint, self::Smallint, self::Mediumint, self::Bigint, self::Decimal, self::Float, self::Double]);
    }
}
