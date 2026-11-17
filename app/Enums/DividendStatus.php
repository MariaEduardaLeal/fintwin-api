<?php

namespace App\Enums;

enum DividendStatus: string
{
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Estimated = 'estimated';
}
