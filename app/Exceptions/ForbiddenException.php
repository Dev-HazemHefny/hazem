<?php

namespace App\Exceptions;

class ForbiddenException extends DomainException
{
    public function __construct(string $message = 'Forbidden.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'FORBIDDEN',
            httpStatus: 403,
        );
    }
}
