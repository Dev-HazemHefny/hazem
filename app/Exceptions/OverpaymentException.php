<?php

namespace App\Exceptions;

class OverpaymentException extends DomainException
{
    public function __construct(int $amountDueCents, int $attemptedCents)
    {
        parent::__construct(
            message: 'Payment amount exceeds amount due on invoice.',
            errorCode: 'OVERPAYMENT',
            details: [
                'amount_due_cents' => $amountDueCents,
                'attempted_cents' => $attemptedCents,
            ],
        );
    }
}
