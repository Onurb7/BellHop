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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            // Always stored/compared uppercase — normalized in
            // PromoCodeRequest and PromoCodeService, not here.
            $table->string('code')->unique();
            // Admin-authored, guest-facing blurb shown once a code is
            // successfully applied (e.g. "10% off during summer months").
            // Optional — the UI falls back to a generic "{percentage}%
            // off" message when blank.
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('percentage');
            // Null means unlimited — usage is counted live from
            // promo_code_redemptions, never a mutable counter column.
            $table->unsignedInteger('max_uses')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
