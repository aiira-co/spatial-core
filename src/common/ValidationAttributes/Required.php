<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

use Attribute;

/**
 * Required Validation Attribute
 * 
 * Ensures a property is not null or empty.
 * 
 * @example
 * class CreateUserDto {
 *     #[Required]
 *     public string $email;
 * }
 * 
 * @package Spatial\Common\ValidationAttributes
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Required extends ValidationAttribute
{
    public function validate(mixed $value, string $propertyName): ValidationResult
    {
        $isValid = match (true) {
            $value === null => false,
            is_string($value) => trim($value) !== '',
            is_array($value) => count($value) > 0,
            default => true
        };

        if (!$isValid) {
            return ValidationResult::failure(
                $this->buildMessage($propertyName),
                $propertyName
            );
        }

        return ValidationResult::success();
    }

    protected function getDefaultMessage(string $propertyName): string
    {
        return "The '{$propertyName}' field is required.";
    }
}
