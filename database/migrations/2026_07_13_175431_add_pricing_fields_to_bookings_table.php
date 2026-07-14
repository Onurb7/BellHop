<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Frozen 30% of the original room charge, set once when the
            // room charge is first recorded — later date/room-change
            // adjustments never retroactively move it, since it tracks
            // money already collected.
            $table->unsignedInteger('deposit_cents')->nullable()->after('status');
            $table->timestamp('last_reminder_sent_at')->nullable()->after('deposit_cents');
            $table->string('last_reminder_type')->nullable()->after('last_reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_cents', 'last_reminder_sent_at', 'last_reminder_type']);
        });
    }
};
