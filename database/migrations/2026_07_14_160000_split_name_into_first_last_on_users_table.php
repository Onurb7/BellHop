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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // PHP split, not a SQL string function — handles single-word
        // names and repeated spaces without surprises. Mirrors the same
        // migration already run against `guests`.
        foreach (DB::table('users')->select('id', 'name')->get() as $user) {
            [$firstName, $lastName] = array_pad(explode(' ', trim($user->name), 2), 2, '');

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $firstName ?: 'User',
                'last_name' => $lastName,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::statement("UPDATE users SET name = trim(concat(first_name, ' ', last_name))");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
