<?php

namespace App\Enums;

enum ServicePricingType: string
{
    case PerNight = 'per_night';
    case Flat = 'flat';
}
