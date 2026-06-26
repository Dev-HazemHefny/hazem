<?php

namespace App\Exceptions;

class PlanIntervalMismatchException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'New plan must use the same billing interval as the current plan.',
            errorCode: 'PLAN_INTERVAL_MISMATCH',
        );
    }
}
