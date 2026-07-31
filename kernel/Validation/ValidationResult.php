<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Validation;

final readonly class ValidationResult
{
    /**
     * @param array<string, mixed> $values
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private array $values,
        private array $errors,
    ) {
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return $this->values;
    }

    public function value(string $field, mixed $default = null): mixed
    {
        return $this->values[$field] ?? $default;
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}

