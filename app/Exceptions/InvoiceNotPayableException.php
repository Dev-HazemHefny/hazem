<?php

namespace App\Exceptions;

class InvoiceNotPayableException extends DomainException
{
    public function __construct(string $status)
    {
        parent::__construct(
            message: "Invoice with status '{$status}' cannot accept payments.",
            errorCode: 'INVOICE_NOT_PAYABLE',
            details: ['status' => $status],
        );
    }
}
