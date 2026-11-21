<?php

namespace App\Enums;

enum AssetClass: string
{
    case Stock = 'stock';
    case Fii = 'fii';
    case FixedIncome = 'fixed_income';
    case Etf = 'etf';
    case Bdr = 'bdr';
    case Crypto = 'crypto';
}
