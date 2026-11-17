<?php

namespace App\Enums;

enum RiskProfile: string
{
    case Conservative = 'conservative';
    case Moderate = 'moderate';
    case Aggressive = 'aggressive';
}
