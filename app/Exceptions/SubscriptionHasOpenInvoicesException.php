<?php

namespace App\Exceptions;

class SubscriptionHasOpenInvoicesException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Subscription has open invoices and cannot be deleted.',
            errorCode: 'SUBSCRIPTION_HAS_OPEN_INVOICES',
        );
    }
}
