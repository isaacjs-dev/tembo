<?php

namespace App\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $resourceKey,
        public readonly int $requested,
        public readonly int $remaining,
    ) {
        parent::__construct("Limite mensal atingido para {$resourceKey}. Saldo disponível: {$remaining}.");
    }
}
