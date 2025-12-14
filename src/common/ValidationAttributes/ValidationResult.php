<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

/**
 * Validation Result
 * 
 * Represents the result of a validation check.
 * 
 * @package Spatial\Common\ValidationAttributes
 */
readonly class ValidationResult
{
    /**
     * @param bool $isValid Whether validation passed
     * @param string|null $error Error message if validation failed
     * @param string|null $propertyName The property that was validated
     */
    public function __construct(
        public bool $isValid,
        public ?string $error = null,
        public ?string $propertyName = null
    ) {}

    /**
     * Create a success result.
     */
    public static function success(): self
    {
        return new self(true);
    }

    /**
     * Create a failure result.
     */
    public static function failure(string $error, string $propertyName): self
    {
        return new self(false, $error, $propertyName);
    }
}
