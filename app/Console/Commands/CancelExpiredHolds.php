<?php

namespace App\Console\Commands;

use App\Services\RoomAvailabilityService;
use Illuminate\Console\Command;

class CancelExpiredHolds extends Command
{
    protected $signature = 'bookings:cancel-expired-holds';

    protected $description = 'Cancel (or delete, if guest-less) pending_payment bookings whose hold has expired';

    public function handle(RoomAvailabilityService $availability): int
    {
        $count = $availability->cancelExpiredHolds();

        $this->info("Cleared {$count} expired hold(s).");

        return self::SUCCESS;
    }
}
