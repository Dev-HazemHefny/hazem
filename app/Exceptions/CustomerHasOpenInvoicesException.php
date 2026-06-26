<?php

namespace App\Exceptions;

class CustomerHasOpenInvoicesException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Customer has open invoices and cannot be deleted.',
            errorCode: 'CUSTOMER_HAS_OPEN_INVOICES',
        );
    }
}
