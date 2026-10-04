<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A user now has one reaction per item, so choosing another emoji
     * replaces the first. Where someone already has several, the newest
     * one is kept.
     */
    public function up(): void
    {
        $keep = DB::table('reactions')
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id', 'reactable_type', 'reactable_id')
            ->pluck('id');

        DB::table('reactions')->whereNotIn('id', $keep)->delete();

        // The new index comes first: on MySQL/MariaDB the old one is what
        // the user_id foreign key uses, and it can only be dropped once
        // another index starting with user_id exists.
        Schema::table('reactions', function (Blueprint $table) {
            $table->unique(['user_id', 'reactable_type', 'reactable_id'], 'reactions_user_target_unique');
        });

        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique('reactions_user_target_emoji_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reactions', function (Blueprint $table) {
            $table->unique(['user_id', 'reactable_type', 'reactable_id', 'emoji'], 'reactions_user_target_emoji_unique');
        });

        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique('reactions_user_target_unique');
        });
    }
};
