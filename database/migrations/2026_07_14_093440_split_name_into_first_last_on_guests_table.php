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
        Schema::table('guests', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('user_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->text('address')->nullable()->after('phone');
        });

        // PHP split, not a SQL string function — handles single-word
        // names and repeated spaces without surprises.
        foreach (DB::table('guests')->select('id', 'name')->get() as $guest) {
            [$firstName, $lastName] = array_pad(explode(' ', trim($guest->name), 2), 2, '');

            DB::table('guests')->where('id', $guest->id)->update([
                'first_name' => $firstName ?: 'Guest',
                'last_name' => $lastName,
            ]);
        }

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
        });

        DB::statement("UPDATE guests SET name = trim(concat(first_name, ' ', last_name))");

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'address']);
        });
    }
};
