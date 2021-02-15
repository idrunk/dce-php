<?php
/**
 * Author: Drunk
 * Date: 2019/9/3 14:08
 */

namespace dce\db\entity\schema;

use dce\db\entity\SchemaAbstract;
use dce\db\SchemaException;

class FieldType extends SchemaAbstract {
    public const INT = 'INT';
    public const TINYINT = 'TINYINT';
    public const SMALLINT = 'SMALLINT';
    public const MEDIUMINT = 'MEDIUMINT';
    public const BIGINT = 'BIGINT';
    public const DECIMAL = 'DECIMAL';
    public const FLOAT = 'FLOAT';
    public const DOUBLE = 'DOUBLE';

    public const VARCHAR = 'VARCHAR';
    public const CHAR = 'CHAR';
    public const TINYTEXT = 'TINYTEXT';
    public const TEXT = 'TEXT';
    public const MEDIUMTEXT = 'MEDIUMTEXT';
    public const LONGTEXT = 'LONGTEXT';
    public const TINYBLOB = 'TINYBLOB';
    public const BLOB = 'BLOB';
    public const MEDIUMBLOB = 'MEDIUMBLOB';
    public const LONGBLOB = 'LONGBLOB';
    public const JSON = 'JSON';

    public const DATE = 'DATE';
    public const DATETIME = 'DATETIME';
    public const TIMESTAMP = 'TIMESTAMP';
    public const TIME = 'TIME';
    public const YEAR = 'YEAR';

    private const LENGTH_MAP = [
        self::INT => [10, 11],
        self::TINYINT => [3, 4],
        self::SMALLINT => [5, 6],
        self::MEDIUMINT => [8, 8],
        self::BIGINT => [20, 20],
    ];

    private string $typeName;

    private int $length;

    private int $precision;

    private bool $unsignedBool;

    private bool $numericBool = false;

    public function __construct(string|null $typeName, int|bool $length, bool|int $isUnsigned, int $precision) {
        if (is_bool($length)) {
            $isUnsigned = $length;
            $length = 0;
        } else if (is_numeric($isUnsigned) && ctype_digit((string) $isUnsigned)) {
            $precision = $isUnsigned;
            $isUnsigned = true;
        }
        if (! ctype_digit((string) $precision)) {
            $precision = 0;
        }
        $typeNameUpper = strtoupper($typeName);
        switch ($typeNameUpper) {
            case self::INT:
            case self::TINYINT:
            case self::SMALLINT:
            case self::MEDIUMINT:
            case self::BIGINT:
                if (! $length) {
                    $length = self::LENGTH_MAP[$typeNameUpper][(int) ! $isUnsigned];
                }
                $this->numericBool = true;
                break;
            case self::DECIMAL:
                if (! $length) {
                    $length = 10;
                }
                $this->numericBool = true;
                $this->precision = $precision;
                break;
            case self::FLOAT:
            case self::DOUBLE:
                $this->numericBool = true;
                $this->precision = $precision;
                break;
            case self::CHAR:
            case self::VARCHAR:
                break;
            case self::TINYTEXT:
            case self::TEXT:
            case self::MEDIUMTEXT:
            case self::LONGTEXT:
                $length = 0;
                break;
            case self::TINYBLOB:
            case self::BLOB:
            case self::MEDIUMBLOB:
            case self::LONGBLOB:
                $length = 0;
                break;
            case self::JSON:
                $length = 0;
                break;
            case self::DATE:
            case self::DATETIME:
            case self::TIMESTAMP:
            case self::TIME:
            case self::YEAR:
                $length = 0;
                break;
            default:
                throw new SchemaException("暂不支持\"{$typeName}\"类型字段");
        }

        $this->typeName = $typeNameUpper;
        $this->length = $length;
        $this->unsignedBool = $isUnsigned;
    }

    public function __toString(): string {
        $sql = $this->getName();
        if ($this->getLength()) {
            $sql .= null === $this->precision ? sprintf('(%d)', $this->length): sprintf('(%d,%d)', $this->length, $this->precision);
        }
        if ($this->isUnsigned()) {
            $sql .= " UNSIGNED";
        }
        return $sql;
    }

    public function getName(): string {
        return $this->typeName;
    }

    public function isNumeric(): bool {
        return $this->unsignedBool;
    }

    public function isUnsigned(): bool {
        return $this->unsignedBool;
    }

    public function getLength(): int {
        return $this->length;
    }

    public function getPrecision(): int {
        return $this->precision;
    }
}
