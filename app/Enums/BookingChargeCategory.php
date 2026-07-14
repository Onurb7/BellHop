<?php

namespace App\Enums;

enum BookingChargeCategory: string
{
    case Room = 'room';
    case DateChange = 'date_change';
    case RoomChange = 'room_change';
}
