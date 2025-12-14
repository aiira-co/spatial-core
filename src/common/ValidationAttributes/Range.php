<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

use Attribute;

/**
 * Range Validation Attribute
 * 
 * Validates that a numeric value falls within a range.
 * 
 * @example
 * class CreateProductDto {
 *     #[Range(0, 1000)]
 *     public float $price;
 * }
 * 
 * @package Spatial\Common\ValidationAttributes
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Range extends ValidationAttribute
{
    public function __construct(
        public int|float $min,
        public int|float $max,
        ?string $message = null
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, string $propertyName): ValidationResult
    {
        if ($value === null) {
            return ValidationResult::success();
        }

        if (!is_numeric($value)) {
            return ValidationResult::failure(
                "The '{$propertyName}' field must be a number.",
                $propertyName
            );
        }

        if ($value < $this->min || $value > $this->max) {
            return ValidationResult::failure(
                $this->buildMessage($propertyName),
                $propertyName
            );
        }

        return ValidationResult::success();
    }

    protected function getDefaultMessage(string $propertyName): string
    {
        return "The '{$propertyName}' field must be between {$this->min} and {$this->max}.";
    }
}
