<?php

namespace App\Services\Sieg\Exceptions;

use RuntimeException;
use Throwable;

/** Base de todos os erros da integração SIEG. Mensagens nunca contêm segredos nem XML. */
class SiegException extends RuntimeException
{
    public function __construct(string $message = '', public readonly ?int $httpStatus = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
