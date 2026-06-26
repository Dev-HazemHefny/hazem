<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Void = 'void';
}
