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
        // One row per booking that redeemed a code — usage against
        // max_uses is a live COUNT() against this table, never a mutable
        // counter, so it can't drift and gives a real audit trail.
        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            // Restrict, not cascade — deleting a code with real discount
            // history is blocked at the app layer (see
            // PromoCodeController::destroy()); this is the DB-level
            // backstop for the same rule.
            $table->foreignId('promo_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('discount_cents');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_code_redemptions');
    }
};
