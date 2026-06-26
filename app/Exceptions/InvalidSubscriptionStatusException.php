<?php

namespace App\Exceptions;

class InvalidSubscriptionStatusException extends DomainException
{
    public function __construct(string $message = 'Subscription cannot be cancelled in its current status.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'INVALID_SUBSCRIPTION_STATUS',
        );
    }
}
