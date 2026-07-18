<?php

namespace App\Enums;

enum Currency: string
{
    case Usd = 'USD';
    case Eur = 'EUR';
    case Gbp = 'GBP';
    case Jpy = 'JPY';
    case Krw = 'KRW';
    case Cad = 'CAD';
    case Aud = 'AUD';
    case Cny = 'CNY';
}
