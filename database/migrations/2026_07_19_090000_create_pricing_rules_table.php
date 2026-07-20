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
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_template')->default(false);
            // Identity key for the 6 seeded templates (weekend, christmas,
            // easter, new_years, summer, winter) — an admin can tune a
            // template's values but never its identity, so this is what
            // idempotent seeding matches on, not the (editable) name.
            $table->string('template_key')->nullable()->unique();
            $table->string('date_kind');
            // Only meaningful for date_kind=day_of_week (the Weekend
            // template only) — array of ints, Carbon's 0=Sun..6=Sat.
            $table->json('days_of_week')->nullable();
            // Only meaningful for date_kind=date_range. For a recurring
            // row only the month/day of these is ever matched — the
            // stored year is a meaningless anchor.
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('recurring')->default(false);
            // Signed — positive is a price increase, negative a discount.
            $table->integer('percentage');
            $table->unsignedTinyInteger('ramp_in_days')->default(0);
            $table->unsignedTinyInteger('ramp_out_days')->default(0);
            // Independent of recurrence — a recurring rule can be turned
            // off without deleting it. Templates seed inactive so a fresh
            // deploy never silently starts charging more.
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
