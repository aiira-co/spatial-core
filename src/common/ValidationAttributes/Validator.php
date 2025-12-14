<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

use ReflectionClass;
use ReflectionProperty;

/**
 * Validator Service
 * 
 * Validates objects against their property validation attributes.
 * 
 * @example
 * $validator = new Validator();
 * $result = $validator->validate($createUserDto);
 * if (!$result->isValid()) {
 *     return $this->badRequest($result->getErrors());
 * }
 * 
 * @package Spatial\Common\ValidationAttributes
 */
class Validator
{
    /**
     * Validate an object against its validation attributes.
     * 
     * @param object $object The object to validate
     * @return ValidationResultCollection
     */
    public function validate(object $object): ValidationResultCollection
    {
        $results = new ValidationResultCollection();
        $reflection = new ReflectionClass($object);

        foreach ($reflection->getProperties() as $property) {
            $this->validateProperty($object, $property, $results);
        }

        return $results;
    }

    /**
     * Validate an array of data against a DTO class without instantiation.
     * 
     * @param array $data The data to validate
     * @param string $dtoClass The DTO class to validate against
     * @return ValidationResultCollection
     */
    public function validateArray(array $data, string $dtoClass): ValidationResultCollection
    {
        $results = new ValidationResultCollection();
        $reflection = new ReflectionClass($dtoClass);

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();
            $value = $data[$propertyName] ?? null;

            $this->validatePropertyAttributes($property, $value, $propertyName, $results);
        }

        return $results;
    }

    /**
     * Validate a single property.
     */
    private function validateProperty(
        object $object,
        ReflectionProperty $property,
        ValidationResultCollection $results
    ): void {
        $propertyName = $property->getName();
        
        // Get the property value
        $property->setAccessible(true);
        $value = $property->isInitialized($object) ? $property->getValue($object) : null;

        $this->validatePropertyAttributes($property, $value, $propertyName, $results);
    }

    /**
     * Run validation attributes on a property value.
     */
    private function validatePropertyAttributes(
        ReflectionProperty $property,
        mixed $value,
        string $propertyName,
        ValidationResultCollection $results
    ): void {
        $attributes = $property->getAttributes(ValidationAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            $validator = $attribute->newInstance();
            $result = $validator->validate($value, $propertyName);

            if (!$result->isValid) {
                $results->addError($result);
            }
        }
    }
}
