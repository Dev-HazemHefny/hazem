<?php

namespace App\Exceptions;

class SamePlanException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Subscription is already on the requested plan.',
            errorCode: 'SAME_PLAN',
        );
    }
}
