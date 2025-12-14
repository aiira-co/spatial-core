<?php

declare(strict_types=1);

namespace Spatial\Common\ValidationAttributes;

/**
 * Validation Result Collection
 * 
 * A collection of validation results with convenience methods.
 * 
 * @package Spatial\Common\ValidationAttributes
 */
class ValidationResultCollection
{
    /** @var ValidationResult[] */
    private array $errors = [];

    /**
     * Add an error result to the collection.
     */
    public function addError(ValidationResult $result): void
    {
        if (!$result->isValid) {
            $this->errors[] = $result;
        }
    }

    /**
     * Check if validation passed (no errors).
     */
    public function isValid(): bool
    {
        return count($this->errors) === 0;
    }

    /**
     * Get all error results.
     * 
     * @return ValidationResult[]
     */
    public function getResults(): array
    {
        return $this->errors;
    }

    /**
     * Get errors as an associative array (property => message).
     * 
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->errors as $result) {
            $errors[$result->propertyName] = $result->error;
        }
        return $errors;
    }

    /**
     * Get errors grouped by property (property => messages[]).
     * 
     * @return array<string, string[]>
     */
    public function getGroupedErrors(): array
    {
        $errors = [];
        foreach ($this->errors as $result) {
            $errors[$result->propertyName][] = $result->error;
        }
        return $errors;
    }

    /**
     * Get all error messages as a flat array.
     * 
     * @return string[]
     */
    public function getMessages(): array
    {
        return array_map(fn($r) => $r->error, $this->errors);
    }

    /**
     * Get the first error, if any.
     */
    public function getFirstError(): ?ValidationResult
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get error count.
     */
    public function count(): int
    {
        return count($this->errors);
    }
}
