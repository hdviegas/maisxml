<?php

namespace App\Services\Sieg\Exceptions;

use Throwable;

/** 429 persistente após o máximo de tentativas. */
class SiegRateLimitedException extends SiegException
{
    public function __construct(string $message = '', public readonly ?int $retryAfterSeconds = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 429, $previous);
    }
}
