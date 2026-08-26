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
        Schema::table('events', function (Blueprint $table) {
            $table->string('place')->nullable()->change();
            $table->decimal('latitude', 10, 7)->nullable()->after('place');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('city')->nullable()->after('longitude');
            $table->string('country')->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'city', 'country']);
            $table->string('place')->nullable(false)->change();
        });
    }
};
