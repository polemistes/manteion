<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The country as an ISO code beside its name, so it can be shown in the
     * viewer's language ("Norge", "Norway") rather than in whatever language
     * the place search answered the creator in.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('country');
        });

        $codes = [
            'NO' => ['Norway', 'Norge', 'Noreg'],
            'SE' => ['Sweden', 'Sverige'],
            'DK' => ['Denmark', 'Danmark'],
            'FI' => ['Finland', 'Suomi'],
            'IS' => ['Iceland', 'Ísland'],
        ];

        foreach ($codes as $code => $names) {
            DB::table('events')->whereIn('country', $names)->update(['country_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
