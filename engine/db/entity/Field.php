<?php
/**
 * Author: Drunk
 * Date: 2019/9/3 11:59
 */

namespace dce\db\entity;

use dce\db\query\builder\RawBuilder;

abstract class Field {
    private string $fieldName;

    private FieldType $fieldType;

    private int $length;

    private int $precision;

    private bool $unsignedBool;

    private bool $primaryKeyBool = false;

    private bool $notNullBool = true;

    private bool $autoIncrementBool = false;

    private string|int|float|RawBuilder|false $defaultValue = false;

    private string|false $commentValue = false;

    public function setName(string $name): static {
        $this->fieldName = $name;
        return $this;
    }

    public function setType(FieldType $fieldType, int $length = 0, bool $isUnsigned = true, int $precision = 0): static {
        $this->fieldType = $fieldType;
        $this->length = $fieldType->getLength($length, $isUnsigned);
        $this->unsignedBool = $isUnsigned;
        $this->precision = $precision;
        return $this;
    }

    public function setPrimary(): static {
        $this->primaryKeyBool = true;
        return $this;
    }

    public function setNull(): static {
        $this->notNullBool = false;
        return $this;
    }

    public function setIncrement(): static {
        $this->autoIncrementBool = true;
        return $this;
    }

    public function setDefault(string|int|float|RawBuilder|false $value): static {
        $this->defaultValue = $value;
        return $this;
    }

    public function setComment(string $value): static {
        $this->commentValue = $value;
        return $this;
    }

    public function getName(): string {
        return $this->fieldName;
    }

    public function getType(): FieldType {
        return $this->fieldType;
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

    public function isPrimaryKey(): bool {
        return $this->primaryKeyBool;
    }

    public function isNotNull(): bool {
        return $this->notNullBool;
    }

    public function isAutoIncrement(): bool {
        return $this->autoIncrementBool;
    }

    public function getDefault(): string|int|float|RawBuilder|false {
        return $this->defaultValue;
    }

    public function getComment(): string|false {
        return $this->commentValue;
    }
}
