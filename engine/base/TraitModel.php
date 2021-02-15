<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/14 0:55
 */

namespace dce\base;

use drunk\Char;

trait TraitModel {
    public static function new(): static {
        return new static;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setProperty (string $name, mixed $value): static {
        $propertyName = Char::camelize($name);
        if (property_exists($this, $propertyName)) {
            $this->$propertyName = $value;
        }
        return $this;
    }

    public function setProperties (array $properties): static {
        foreach ($properties as $name => $property) {
            $this->setProperty($name, $property);
        }
        return $this;
    }

    public function arrayify (): array {
        $properties = get_object_vars($this);
        foreach ($properties as $k => $property) {
            if (! is_scalar($property)) {
                unset($properties[$k]);
            }
        }
        return $properties;
    }
}
