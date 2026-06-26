<?php

namespace App\Exceptions;

class NotImplementedException extends DomainException
{
    public function __construct(string $message = 'This feature is not implemented.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'NOT_IMPLEMENTED',
            httpStatus: 501,
        );
    }
}
