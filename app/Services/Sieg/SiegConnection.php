<?php

namespace App\Services\Sieg;

/**
 * Credenciais de uma conta SIEG em memória.
 *
 * `limiterKey` identifica a API Key nos limites de taxa e no cache do JWT sem
 * expor a chave (hash). Nunca serializar/logar `apiKey`.
 */
final class SiegConnection
{
    public readonly string $limiterKey;

    public function __construct(
        #[\SensitiveParameter]
        public readonly string $apiKey,
    ) {
        $this->limiterKey = substr(hash('sha256', $apiKey), 0, 32);
    }

    public function __debugInfo(): array
    {
        return ['limiterKey' => $this->limiterKey, 'apiKey' => '***'];
    }
}
