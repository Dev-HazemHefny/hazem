<?php

namespace App\Exceptions;

class PlanInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Subscription plan is inactive.',
            errorCode: 'PLAN_INACTIVE',
        );
    }
}
