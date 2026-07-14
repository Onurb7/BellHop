<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Raw SQL, not Blueprint's ->change(), to avoid a doctrine/dbal
        // dependency — matches how this table's original migration
        // already drops to raw DB::statement for anything beyond
        // Blueprint's comfort zone. A null guest_id marks an in-progress
        // walk-in draft (room locked, guest details not collected yet).
        DB::statement('ALTER TABLE bookings ALTER COLUMN guest_id DROP NOT NULL');

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('last_reminder_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        DB::statement('ALTER TABLE bookings ALTER COLUMN guest_id SET NOT NULL');
    }
};
