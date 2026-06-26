<?php

namespace App\Exceptions;

class InvalidPaymentAmountException extends DomainException
{
    public function __construct(string $message = 'Payment amount must be greater than zero.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'VALIDATION_ERROR',
            details: ['amount_cents' => [$message]],
        );
    }
}
