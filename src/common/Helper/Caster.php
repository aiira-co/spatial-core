<?php
declare(strict_types=1);
namespace Spatial\Common\Helper;
use http\Exception\InvalidArgumentException;

class Caster
{
    /**
     * Casts an associative array or JSON string into an object of the given class.
     *
     * @param string $className The fully qualified class name of the target object.
     * @param string|array $data The data to cast (JSON string or associative array).
     * @return object The instantiated object with properties set via setters.
     * @throws \ReflectionException If the class does not exist or properties are inaccessible.
     */
    public static function castToObject(string $className, string|array $data): object
    {
        // Ensure the class exists
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class $className does not exist.");
        }

        // Decode JSON string if provided
        if (is_string($data)) {
            $data = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException("Invalid JSON string provided.");
            }
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException("Data must be an array.");
        }

        // Create a new instance of the class
        $reflectionClass = new \ReflectionClass($className);
        $object = $reflectionClass->newInstanceWithoutConstructor();

        // Iterate through the class properties
        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();

            if (!array_key_exists($propertyName, $data)) {
                // Check if the property is nullable or has a default value
                $propertyType = $property->getType();
                if (!$propertyType || $propertyType->allowsNull()) {
                    continue;
                }
                throw new InvalidArgumentException("Missing required property: $propertyName");
            }

            $value = $data[$propertyName];
            $setterMethod = 'set' . ucfirst($propertyName);

            try {
                if ($reflectionClass->hasMethod($setterMethod)) {
                    $setter = $reflectionClass->getMethod($setterMethod);

                    if (!$setter->isPublic()) {
                        continue;
                    }

                    $parameters = $setter->getParameters();
                    if (count($parameters) === 1) {
                        $parameter = $parameters[0];
                        $parameterType = $parameter->getType();

                        if ($parameterType) {
                            $value = self::castValueToType($value, $parameterType);
                        }

                        $setter->invoke($object, $value);
                    }
                }
            } catch (\Throwable $e) {
                throw new InvalidArgumentException(
                    "Invalid payload for property '$propertyName': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $object;
    }

    /**
     * Cast a value to a specified ReflectionType.
     *
     * @param mixed $value
     * @param \ReflectionType $type
     * @return mixed
     * @throws \ReflectionException
     */
    private static function castValueToType(mixed $value, \ReflectionType $type): mixed
    {
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                try {
                    return self::castValueToType($value, $unionType);
                } catch (\InvalidArgumentException $exception) {
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

        if ($typeName === 'Collection' && is_array($value)) {
            $collection = new Collection();
            foreach ($value as $item) {
                $collection->add($item);
            }
            return $collection;
        }

        if (class_exists($typeName)) {
            return self::castToObject($typeName, $value);
        }

        throw new InvalidArgumentException("Cannot cast value to type: $typeName");
    }

}
