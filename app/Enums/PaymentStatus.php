<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
