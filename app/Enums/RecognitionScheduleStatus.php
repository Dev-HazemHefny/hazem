<?php

namespace App\Enums;

enum RecognitionScheduleStatus: string
{
    case Pending = 'pending';
    case Recognized = 'recognized';
    case Cancelled = 'cancelled';
}
