<?php

declare(strict_types=1);

namespace Spatial\Common\Helper;

use ReflectionException;
use ReflectionProperty;
use ReflectionType;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

class Caster
{
    /**
     * Casts an associative array or JSON string into an object of the given class.
     *
     * @param string $className The fully qualified class name of the target object.
     * @param array|string $data The data to cast (JSON string or associative array).
     * @return object The instantiated object with properties set via setters.
     * @throws ReflectionException If the class does not exist or properties are inaccessible.
     * @throws InvalidArgumentException If the data is invalid.
     * @throws RuntimeException If an error occurs during casting.
     */
    public static function castToObject(string $className, array|string $data): object
    {
        // Ensure the class exists
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class '$className' does not exist.");
        }

        // Decode JSON string if provided
        if (is_string($data)) {
            $data = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON string provided.');
            }
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Data must be an array.');
        }

        $classInstance = new $className();
        $reflectionClass = new \ReflectionClass($className);

        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();

            if (!array_key_exists($propertyName, $data)) {
                // Check for nullability and default values
                $propertyType = $property->getType();
                if (!$propertyType || $propertyType->allowsNull() || $property->hasDefaultValue()) {
                    continue;
                }
                throw new InvalidArgumentException("Missing required property: '$propertyName'");
            }

            $value = $data[$propertyName];

            try {
                if (!$property->isPublic()) {
                    continue;
                }

                // Check for setter hook (PHP 8.4+)
                // if (PHP_VERSION_ID >= 80400 && !$property->hasHook(\PropertyHookType::Set)) {
                //     continue;
                // }

                $propertyType = $property->getType();
                if ($propertyType) {
                    $value = self::castValueToType($value, $propertyType);
                }

                $classInstance->{$propertyName} = $value;
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Error casting property '$propertyName': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $classInstance;
    }

    /**
     * Cast a value to a specified ReflectionType.
     *
     * @param mixed $value
     * @param ReflectionType $type
     * @return mixed
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private static function castValueToType(mixed $value, ReflectionType $type): mixed
    {
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                try {
                    return self::castValueToType($value, $unionType);
                } catch (RuntimeException) {
                    // Try next type
                }
            }
            throw new InvalidArgumentException("Value does not match any of the union types.");
        }

        if ($type->allowsNull() && $value === null) {
            return null;
        }

        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            settype($value, $typeName);
            return $value;
        }

        if ($typeName === 'array') {
            if (!is_array($value)) {
                throw new InvalidArgumentException("Value must be an array.");
            }
            return $value;
        }

        if (class_exists($typeName)) {
            return self::castToObject($typeName, $value);
        }

        throw new RuntimeException("Cannot cast value to type: $typeName");
    }
}