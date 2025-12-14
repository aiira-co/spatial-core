<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

use Attribute;

/**
 * MinLength Validation Attribute
 * 
 * Validates that a string or array has at least N elements/characters.
 * 
 * @example
 * class CreateUserDto {
 *     #[MinLength(8)]
 *     public string $password;
 * }
 * 
 * @package Spatial\Common\ValidationAttributes
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MinLength extends ValidationAttribute
{
    public function __construct(
        public int $min,
        ?string $message = null
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, string $propertyName): ValidationResult
    {
        if ($value === null) {
            return ValidationResult::success(); // Use #[Required] for null checks
        }

        $length = match (true) {
            is_string($value) => mb_strlen($value),
            is_array($value) => count($value),
            default => null
        };

        if ($length === null) {
            return ValidationResult::failure(
                "The '{$propertyName}' field must be a string or array.",
                $propertyName
            );
        }

        if ($length < $this->min) {
            return ValidationResult::failure(
                $this->buildMessage($propertyName),
                $propertyName
            );
        }

        return ValidationResult::success();
    }

    protected function getDefaultMessage(string $propertyName): string
    {
        return "The '{$propertyName}' field must be at least {$this->min} characters.";
    }
}
