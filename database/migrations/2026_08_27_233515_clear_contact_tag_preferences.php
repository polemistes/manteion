<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * contact_tag_preferences rows used to mean "only notify me about
     * these tags" (an allow-list); they now mean "don't notify me about
     * these tags" (a deny-list). Keeping old rows around would silently
     * flip their meaning for anyone who'd already saved a preference, so
     * this clears them back to the default (notify about everything).
     */
    public function up(): void
    {
        DB::table('contact_tag_preferences')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
