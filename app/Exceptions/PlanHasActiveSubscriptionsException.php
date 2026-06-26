<?php

namespace App\Exceptions;

class PlanHasActiveSubscriptionsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Plan has active subscriptions and cannot be deactivated.',
            errorCode: 'PLAN_HAS_ACTIVE_SUBSCRIPTIONS',
        );
    }
}
