<?php

namespace App\Enums;

enum PricingRuleDateKind: string
{
    case DayOfWeek = 'day_of_week';
    case DateRange = 'date_range';
}
