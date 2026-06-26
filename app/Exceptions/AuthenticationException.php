<?php

namespace App\Exceptions;

class AuthenticationException extends DomainException
{
    public function __construct(string $message = 'Invalid credentials.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'UNAUTHENTICATED',
            httpStatus: 401,
        );
    }
}
