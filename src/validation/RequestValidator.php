<?php

declare(strict_types=1);

namespace Spatial\Validation;

use ReflectionClass;
use ReflectionProperty;

/**
 * Request Validator
 * 
 * Validates request data against DTO validation attributes.
 * 
 * @package Spatial\Validation
 */
class RequestValidator
{
    private array $errors = [];

    /**
     * Validate data against a DTO class.
     *
     * @param object $dto The DTO instance with data
     * @return ValidationResult
     */
    public function validate(object $dto): ValidationResult
    {
        $this->errors = [];
        
        $reflection = new ReflectionClass($dto);
        
        foreach ($reflection->getProperties() as $property) {
            $this->validateProperty($dto, $property);
        }

        return new ValidationResult($this->errors);
    }

    /**
     * Validate a single property.
     */
    private function validateProperty(object $dto, ReflectionProperty $property): void
    {
        $property->setAccessible(true);
        $propertyName = $property->getName();
        $value = $property->isInitialized($dto) ? $property->getValue($dto) : null;

        $attributes = $property->getAttributes();
        
        foreach ($attributes as $attribute) {
            $attrClass = $attribute->getName();
            $attrInstance = $attribute->newInstance();

            // Check for validation attributes
            if (method_exists($attrInstance, 'validate')) {
                $result = $attrInstance->validate($value, $propertyName);
                if ($result !== true) {
                    $this->errors[$propertyName][] = $result;
                }
            }
            
            // Handle built-in validation attributes
            $this->handleBuiltinValidation($attrClass, $attrInstance, $value, $propertyName);
        }
    }

    /**
     * Handle built-in validation attributes.
     */
    private function handleBuiltinValidation(
        string $attrClass,
        object $attrInstance,
        mixed $value,
        string $propertyName
    ): void {
        $shortName = class_exists($attrClass) 
            ? (new ReflectionClass($attrClass))->getShortName() 
            : basename(str_replace('\\', '/', $attrClass));

        switch ($shortName) {
            case 'Required':
                if ($value === null || $value === '') {
                    $this->errors[$propertyName][] = "{$propertyName} is required";
                }
                break;

            case 'Email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$propertyName][] = "{$propertyName} must be a valid email";
                }
                break;

            case 'Range':
                if ($value !== null) {
                    $min = $attrInstance->min ?? null;
                    $max = $attrInstance->max ?? null;
                    if ($min !== null && $value < $min) {
                        $this->errors[$propertyName][] = "{$propertyName} must be at least {$min}";
                    }
                    if ($max !== null && $value > $max) {
                        $this->errors[$propertyName][] = "{$propertyName} must be at most {$max}";
                    }
                }
                break;

            case 'MinLength':
                $min = $attrInstance->min ?? $attrInstance->value ?? 0;
                if ($value !== null && strlen((string)$value) < $min) {
                    $this->errors[$propertyName][] = "{$propertyName} must be at least {$min} characters";
                }
                break;

            case 'MaxLength':
                $max = $attrInstance->max ?? $attrInstance->value ?? PHP_INT_MAX;
                if ($value !== null && strlen((string)$value) > $max) {
                    $this->errors[$propertyName][] = "{$propertyName} must be at most {$max} characters";
                }
                break;

            case 'Regex':
            case 'Pattern':
                $pattern = $attrInstance->pattern ?? $attrInstance->value ?? '';
                if ($value !== null && $pattern && !preg_match($pattern, (string)$value)) {
                    $this->errors[$propertyName][] = "{$propertyName} format is invalid";
                }
                break;

            case 'Url':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->errors[$propertyName][] = "{$propertyName} must be a valid URL";
                }
                break;

            case 'In':
                $allowed = $attrInstance->values ?? $attrInstance->options ?? [];
                if ($value !== null && !in_array($value, $allowed, true)) {
                    $values = implode(', ', $allowed);
                    $this->errors[$propertyName][] = "{$propertyName} must be one of: {$values}";
                }
                break;
        }
    }
}

/**
 * Validation Result
 */
class ValidationResult
{
    public function __construct(
        private array $errors = []
    ) {}

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        foreach ($this->errors as $field => $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors
        ];
    }
}
