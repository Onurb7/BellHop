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
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // Nullable — a purchased line item must survive the catalog
            // service later being deleted (admin CRUD hard-deletes).
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            // Snapshotted from the service at purchase time, same
            // reasoning as booking_charges: a later rename/price change/
            // deletion must never retroactively change a past purchase.
            $table->string('name');
            $table->string('pricing_type');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('nights')->nullable();
            $table->unsignedInteger('line_total_cents');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_services');
    }
};
