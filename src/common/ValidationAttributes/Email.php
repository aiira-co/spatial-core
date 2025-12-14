<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

use Attribute;

/**
 * Email Validation Attribute
 * 
 * Validates that a string is a valid email address.
 * 
 * @example
 * class CreateUserDto {
 *     #[Email]
 *     public string $email;
 * }
 * 
 * @package Spatial\Common\ValidationAttributes
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Email extends ValidationAttribute
{
    public function validate(mixed $value, string $propertyName): ValidationResult
    {
        if ($value === null || $value === '') {
            return ValidationResult::success(); // Use #[Required] for null checks
        }

        if (!is_string($value)) {
            return ValidationResult::failure(
                "The '{$propertyName}' field must be a string.",
                $propertyName
            );
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ValidationResult::failure(
                $this->buildMessage($propertyName),
                $propertyName
            );
        }

        return ValidationResult::success();
    }

    protected function getDefaultMessage(string $propertyName): string
    {
        return "The '{$propertyName}' field must be a valid email address.";
    }
}
