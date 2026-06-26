<?php

namespace App\Exceptions;

class InvalidPlanChangeDateException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Effective date must fall within the current billing period.',
            errorCode: 'INVALID_PLAN_CHANGE_DATE',
        );
    }
}
