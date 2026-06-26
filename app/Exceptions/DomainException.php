<?php

namespace App\Exceptions;

use Exception;

class DomainException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'DOMAIN_ERROR',
        public readonly ?array $details = null,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message, $httpStatus);
    }
}
