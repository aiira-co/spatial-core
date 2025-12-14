<?php

declare(strict_types=1);

namespace Spatial\Common\Helper;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use ReflectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;
use RuntimeException;
use InvalidArgumentException;
use Throwable;
use ValueError;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Spatial\Common\ValidationAttributes\Validator;
use Spatial\Common\ValidationAttributes\ValidationResultCollection;

/**
 * Caster - JSON/Array to PHP Object Hydrator
 * 
 * Casts JSON strings or associative arrays into strongly-typed PHP objects,
 * with support for nested objects, enums, dates, collections, and validation.
 * 
 * @package Spatial\Common\Helper
 */
class Caster
{
    /**
     * Enable automatic validation after casting.
     */
    private static bool $autoValidate = false;

    /**
     * Property name transformation strategy.
     */
    private static string $nameStrategy = 'none'; // 'none', 'camelCase', 'snake_case'

    /**
     * Cache for reflection classes.
     * @var array<string, ReflectionClass>
     */
    private static array $reflectionCache = [];

    /**
     * Configure Caster behavior.
     * 
     * @param array{autoValidate?: bool, nameStrategy?: string} $options
     */
    public static function configure(array $options): void
    {
        if (isset($options['autoValidate'])) {
            self::$autoValidate = $options['autoValidate'];
        }
        if (isset($options['nameStrategy'])) {
            self::$nameStrategy = $options['nameStrategy'];
        }
    }

    /**
     * Casts an associative array or JSON string into an object of the given class.
     *
     * @template T of object
     * @param class-string<T> $className The fully qualified class name of the target object.
     * @param array|string $data The data to cast (JSON string or associative array).
     * @param bool|null $validate Override auto-validation setting for this call.
     * @return T The instantiated object with properties set.
     * @throws ReflectionException If the class does not exist or properties are inaccessible.
     * @throws InvalidArgumentException If the data is invalid.
     * @throws RuntimeException If an error occurs during casting.
     * @throws CasterValidationException If validation fails.
     */
    public static function cast(string $className, array|string $data, ?bool $validate = null): object
    {
        // Ensure the class exists
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class '$className' does not exist.");
        }

        // Decode JSON string if provided
        $data = self::normalizeData($data);

        // Get or create reflection class (cached)
        $reflectionClass = self::getReflection($className);

        // Create instance
        $classInstance = self::createInstance($reflectionClass);

        // Hydrate properties
        self::hydrateProperties($classInstance, $reflectionClass, $data);

        // Validate if enabled
        $shouldValidate = $validate ?? self::$autoValidate;
        if ($shouldValidate) {
            self::validateObject($classInstance);
        }

        return $classInstance;
    }

    /**
     * Cast to object (legacy alias).
     * 
     * @deprecated Use cast() instead
     */
    public static function castToObject(string $className, array|string $data): object
    {
        return self::cast($className, $data);
    }

    /**
     * Cast an array of items to a collection of objects.
     * 
     * @template T of object
     * @param class-string<T> $className The class to cast each item to.
     * @param array $items Array of data items.
     * @return T[] Array of cast objects.
     */
    public static function castMany(string $className, array $items): array
    {
        return array_map(
            fn($item) => self::cast($className, $item),
            $items
        );
    }

    /**
     * Cast with validation and return result object.
     * 
     * @template T of object
     * @param class-string<T> $className
     * @param array|string $data
     * @return CasterResult<T>
     */
    public static function tryCast(string $className, array|string $data): CasterResult
    {
        try {
            $object = self::cast($className, $data, false);
            $validator = new Validator();
            $validationResult = $validator->validate($object);
            
            return new CasterResult($object, $validationResult);
        } catch (Throwable $e) {
            return CasterResult::failure($e->getMessage());
        }
    }

    /**
     * Normalize input data (decode JSON if string).
     */
    private static function normalizeData(array|string $data): array
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(
                    'Invalid JSON string: ' . json_last_error_msg()
                );
            }
            return $decoded;
        }

        return $data;
    }

    /**
     * Get or create a cached ReflectionClass.
     */
    private static function getReflection(string $className): ReflectionClass
    {
        if (!isset(self::$reflectionCache[$className])) {
            self::$reflectionCache[$className] = new ReflectionClass($className);
        }
        return self::$reflectionCache[$className];
    }

    /**
     * Create an instance of the class.
     */
    private static function createInstance(ReflectionClass $reflectionClass): object
    {
        $constructor = $reflectionClass->getConstructor();
        
        // If no constructor or constructor has no required params, use new
        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            return $reflectionClass->newInstance();
        }

        // Use newInstanceWithoutConstructor for classes with required constructor params
        return $reflectionClass->newInstanceWithoutConstructor();
    }

    /**
     * Hydrate object properties from data.
     */
    private static function hydrateProperties(
        object $instance,
        ReflectionClass $reflectionClass,
        array $data
    ): void {
        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            $dataKey = self::transformPropertyName($propertyName);

            // Check if data exists for this property
            if (!array_key_exists($dataKey, $data) && !array_key_exists($propertyName, $data)) {
                self::handleMissingProperty($property);
                continue;
            }

            $value = $data[$dataKey] ?? $data[$propertyName];

            try {
                self::setPropertyValue($instance, $property, $value);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Error casting property '$propertyName': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Transform property name based on naming strategy.
     */
    private static function transformPropertyName(string $name): string
    {
        return match (self::$nameStrategy) {
            'snake_case' => self::toSnakeCase($name),
            'camelCase' => self::toCamelCase($name),
            default => $name
        };
    }

    /**
     * Convert to snake_case.
     */
    private static function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
    }

    /**
     * Convert to camelCase.
     */
    private static function toCamelCase(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name))));
    }

    /**
     * Handle a missing property in the data.
     */
    private static function handleMissingProperty(ReflectionProperty $property): void
    {
        $propertyType = $property->getType();
        
        // Skip if property has default value, is nullable, or has no type
        if ($property->hasDefaultValue() 
            || $propertyType === null 
            || $propertyType->allowsNull()
        ) {
            return;
        }

        throw new InvalidArgumentException(
            "Missing required property: '{$property->getName()}'"
        );
    }

    /**
     * Set a property value on the instance.
     */
    private static function setPropertyValue(
        object $instance,
        ReflectionProperty $property,
        mixed $value
    ): void {
        // Skip non-public properties without making them accessible
        if (!$property->isPublic()) {
            // Try setter method
            $setter = 'set' . ucfirst($property->getName());
            if (method_exists($instance, $setter)) {
                $instance->$setter($value);
                return;
            }
            // If no setter and not public, skip
            return;
        }

        $propertyType = $property->getType();
        if ($propertyType !== null) {
            $value = self::castValue($value, $propertyType, $property->getName());
        }

        $property->setValue($instance, $value);
    }

    /**
     * Cast a value to match a ReflectionType.
     */
    private static function castValue(
        mixed $value,
        ReflectionType $type,
        string $propertyName
    ): mixed {
        // Handle union types
        if ($type instanceof ReflectionUnionType) {
            return self::castUnionType($value, $type, $propertyName);
        }

        // Handle intersection types (PHP 8.1+)
        if ($type instanceof ReflectionIntersectionType) {
            throw new RuntimeException(
                "Intersection types are not supported for property: $propertyName"
            );
        }

        // Handle nullable
        if ($type->allowsNull() && $value === null) {
            return null;
        }

        if (!($type instanceof ReflectionNamedType)) {
            return $value;
        }

        return self::castNamedType($value, $type, $propertyName);
    }

    /**
     * Cast a value for a union type (try each type until one succeeds).
     */
    private static function castUnionType(
        mixed $value,
        ReflectionUnionType $type,
        string $propertyName
    ): mixed {
        $errors = [];
        
        foreach ($type->getTypes() as $unionType) {
            try {
                return self::castValue($value, $unionType, $propertyName);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        throw new InvalidArgumentException(
            "Value for '$propertyName' does not match any union type: " . 
            implode(', ', $errors)
        );
    }

    /**
     * Cast a value for a named type.
     */
    private static function castNamedType(
        mixed $value,
        ReflectionNamedType $type,
        string $propertyName
    ): mixed {
        $typeName = $type->getName();

        // Handle built-in types
        if ($type->isBuiltin()) {
            return self::castBuiltinType($value, $typeName);
        }

        // Handle enums
        if (enum_exists($typeName)) {
            return self::castEnum($value, $typeName, $propertyName);
        }

        // Handle DateTime types
        if (self::isDateTimeType($typeName)) {
            return self::castDateTime($value, $typeName, $propertyName);
        }

        // Handle classes (recursive)
        if (class_exists($typeName)) {
            if (is_array($value)) {
                return self::cast($typeName, $value);
            }
            throw new InvalidArgumentException(
                "Property '$propertyName' expects object data for class '$typeName'"
            );
        }

        // Handle interfaces (just return value if already correct type)
        if (interface_exists($typeName) && $value instanceof $typeName) {
            return $value;
        }

        throw new RuntimeException("Cannot cast value to type: $typeName");
    }

    /**
     * Cast a value to a built-in type.
     */
    private static function castBuiltinType(mixed $value, string $typeName): mixed
    {
        return match ($typeName) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => self::castToBoolean($value),
            'array' => is_array($value) ? $value : [$value],
            'object' => (object) $value,
            'mixed' => $value,
            default => $value
        };
    }

    /**
     * Cast a value to boolean (handles string 'true'/'false').
     */
    private static function castToBoolean(mixed $value): bool
    {
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true' || $lower === '1') return true;
            if ($lower === 'false' || $lower === '0' || $lower === '') return false;
        }
        return (bool) $value;
    }

    /**
     * Cast a value to an enum.
     */
    private static function castEnum(mixed $value, string $enumClass, string $propertyName): mixed
    {
        try {
            // Try backed enum first
            if (method_exists($enumClass, 'from')) {
                return $enumClass::from($value);
            }
            // Try unit enum by name
            if (method_exists($enumClass, 'cases')) {
                foreach ($enumClass::cases() as $case) {
                    if ($case->name === $value) {
                        return $case;
                    }
                }
            }
            throw new InvalidArgumentException("Value not found in enum");
        } catch (ValueError $e) {
            throw new InvalidArgumentException(
                "Invalid value '{$value}' for enum $enumClass on property '$propertyName'"
            );
        }
    }

    /**
     * Check if a type is a DateTime type.
     */
    private static function isDateTimeType(string $typeName): bool
    {
        return in_array($typeName, [
            DateTime::class,
            DateTimeImmutable::class,
            DateTimeInterface::class,
            'DateTime',
            'DateTimeImmutable',
        ], true);
    }

    /**
     * Cast a value to a DateTime type.
     */
    private static function castDateTime(
        mixed $value,
        string $typeName,
        string $propertyName
    ): DateTimeInterface {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        try {
            // Handle timestamps
            if (is_int($value)) {
                $dateTime = new DateTime();
                $dateTime->setTimestamp($value);
                return $typeName === DateTimeImmutable::class 
                    ? DateTimeImmutable::createFromMutable($dateTime)
                    : $dateTime;
            }

            // Handle string dates
            if (is_string($value)) {
                return $typeName === DateTimeImmutable::class
                    ? new DateTimeImmutable($value)
                    : new DateTime($value);
            }

            throw new InvalidArgumentException("Cannot parse date from provided value");
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                "Invalid date value for property '$propertyName': " . $e->getMessage()
            );
        }
    }

    /**
     * Validate an object and throw if invalid.
     */
    private static function validateObject(object $object): void
    {
        $validator = new Validator();
        $result = $validator->validate($object);

        if (!$result->isValid()) {
            throw new CasterValidationException(
                "Validation failed: " . implode(', ', $result->getMessages()),
                $result
            );
        }
    }

    /**
     * Clear the reflection cache.
     */
    public static function clearCache(): void
    {
        self::$reflectionCache = [];
    }
}

/**
 * Result of a cast operation with validation.
 * 
 * @template T of object
 */
class CasterResult
{
    public function __construct(
        public readonly ?object $object,
        public readonly ?ValidationResultCollection $validation = null,
        public readonly ?string $error = null
    ) {}

    public function isValid(): bool
    {
        if ($this->error !== null) {
            return false;
        }
        return $this->validation?->isValid() ?? true;
    }

    public function getErrors(): array
    {
        if ($this->error !== null) {
            return ['_error' => $this->error];
        }
        return $this->validation?->getErrors() ?? [];
    }

    public static function failure(string $error): self
    {
        return new self(null, null, $error);
    }
}

/**
 * Exception thrown when caster validation fails.
 */
class CasterValidationException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly ValidationResultCollection $validationResult,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->validationResult->getErrors();
    }
}
