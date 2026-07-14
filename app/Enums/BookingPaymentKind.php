<?php

namespace App\Enums;

enum BookingPaymentKind: string
{
    case Deposit = 'deposit';
    case Balance = 'balance';
    case Additional = 'additional';
}
