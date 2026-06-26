<?php

namespace App\Exceptions;

class CustomerHasActiveSubscriptionsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Customer has active subscriptions and cannot be deleted.',
            errorCode: 'CUSTOMER_HAS_ACTIVE_SUBSCRIPTIONS',
        );
    }
}
