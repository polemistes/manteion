<?php

use App\Services\TimeZones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A person reads times in their own time zone (reported by their
     * device; until then the app's display time zone), and an event keeps
     * the zone of the place it happens in. Events so far were all written
     * in the display time zone: one with a pin gets its pin's zone, the
     * rest get the display zone.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('locale');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('ends_at');
        });

        $displayTimeZone = (string) config('app.display_timezone');

        DB::table('events')->select(['id', 'latitude', 'longitude', 'country_code'])->orderBy('id')->each(function (object $event) use ($displayTimeZone): void {
            $atPin = $event->latitude === null || $event->longitude === null
                ? null
                : TimeZones::forPlace((float) $event->latitude, (float) $event->longitude, $event->country_code);

            DB::table('events')->where('id', $event->id)->update(['timezone' => $atPin ?? $displayTimeZone]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
