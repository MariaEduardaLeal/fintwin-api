<?php

namespace App\Enums;

enum DividendType: string
{
    case Dividend = 'dividend';
    case Jcp = 'jcp';
    case Yield = 'yield';
    case Amortization = 'amortization';
}
