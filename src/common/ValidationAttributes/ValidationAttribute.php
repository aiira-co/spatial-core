<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

use Attribute;

/**
 * Base class for all validation attributes.
 * 
 * Extend this class to create custom validation rules.
 * 
 * @package Spatial\Common\ValidationAttributes
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
abstract class ValidationAttribute
{
    /**
     * @param string|null $message Custom error message
     */
    public function __construct(
        public ?string $message = null
    ) {}

    /**
     * Validate a value against this rule.
     * 
     * @param mixed $value The value to validate
     * @param string $propertyName The property name (for error messages)
     * @return ValidationResult
     */
    abstract public function validate(mixed $value, string $propertyName): ValidationResult;

    /**
     * Get the default error message for this validation rule.
     */
    abstract protected function getDefaultMessage(string $propertyName): string;

    /**
     * Build the error message.
     */
    protected function buildMessage(string $propertyName): string
    {
        return $this->message ?? $this->getDefaultMessage($propertyName);
    }
}
