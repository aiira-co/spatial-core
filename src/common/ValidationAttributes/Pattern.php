<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

use Attribute;

/**
 * Pattern Validation Attribute
 * 
 * Validates that a string matches a regular expression.
 * 
 * @example
 * class CreateUserDto {
 *     #[Pattern('/^[a-zA-Z0-9_]+$/')]
 *     public string $username;
 * }
 * 
 * @package Spatial\Common\ValidationAttributes
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Pattern extends ValidationAttribute
{
    public function __construct(
        public string $pattern,
        ?string $message = null
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, string $propertyName): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success();
        }

        if (!is_string($value)) {
            return ValidationResult::failure(
                "The '{$propertyName}' field must be a string.",
                $propertyName
            );
        }

        if (!preg_match($this->pattern, $value)) {
            return ValidationResult::failure(
                $this->buildMessage($propertyName),
                $propertyName
            );
        }

        return ValidationResult::success();
    }

    protected function getDefaultMessage(string $propertyName): string
    {
        return "The '{$propertyName}' field format is invalid.";
    }
}
