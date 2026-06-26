<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case CreditMemo = 'credit_memo';
    case Void = 'void';
}
