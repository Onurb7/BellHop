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
        // Needed for the GiST index behind the exclusion constraint below.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->string('status')->default('confirmed');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_check_out_after_check_in CHECK (check_out > check_in)');

        // Half-open range ([check_in, check_out)) so a checkout day equals
        // the next booking's check-in day without registering as an overlap.
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD COLUMN stayrange daterange
            GENERATED ALWAYS AS (daterange(check_in, check_out, '[)')) STORED
        SQL);

        // Hard DB-level guarantee against double-booking a room — cancelled/
        // no_show bookings are excluded so they don't block rebooking the
        // same dates. This is the source of truth, not an app-level check.
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_no_overlapping_stays
            EXCLUDE USING gist (room_id WITH =, stayrange WITH &&)
            WHERE (status NOT IN ('cancelled', 'no_show'))
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
