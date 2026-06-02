<?php

namespace App\Support\Tenancy;

use RuntimeException;

class TenantResolutionException extends RuntimeException
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $reasonCode,
        private readonly array $context = [],
        string $message = 'Tenant context resolution failed.',
    ) {
        parent::__construct($message);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function forbidden(string $reasonCode, array $context = [], string $message = 'Tenant access denied.'): self
    {
        return new self(403, $reasonCode, $context, $message);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function unprocessable(string $reasonCode, array $context = [], string $message = 'Tenant context is inconsistent.'): self
    {
        return new self(422, $reasonCode, $context, $message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    /**
     * @return array<string,mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
