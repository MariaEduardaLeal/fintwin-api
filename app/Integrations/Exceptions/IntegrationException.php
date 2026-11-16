<?php

namespace App\Integrations\Exceptions;

use RuntimeException;
use Throwable;

class IntegrationException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct("[{$provider}] {$message}", $statusCode ?? 0, $previous);
    }
}
