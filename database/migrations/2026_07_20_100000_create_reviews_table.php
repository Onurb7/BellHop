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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // The entire access control for the guest-facing /review/{uuid}
            // page — no login required, the token itself is the secret.
            $table->uuid('uuid')->unique();
            // Set once, at checkout time + 3 days. The daily
            // reviews:send-followups job sweeps whereNull('sent_at')
            // AND where send_at has passed — it never deletes this row,
            // since the link must keep resolving whenever the guest
            // actually opens the email.
            $table->dateTime('send_at');
            $table->dateTime('sent_at')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('body')->nullable();
            // Presence of this is what makes a review "real" — eligible
            // to be featured, shown to admins as reviewed rather than
            // pending.
            $table->dateTime('submitted_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
