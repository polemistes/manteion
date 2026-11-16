<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How an event's time zone is decided (App\Enums\EventTimeZoneMode):
     * from the place, as every event so far, chosen by the host, or none.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('timezone_mode')->default('auto')->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('timezone_mode');
        });
    }
};
