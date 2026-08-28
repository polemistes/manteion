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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_friends_list')->default(true)->after('allow_public_follow');
            $table->boolean('appear_in_friends_lists')->default(true)->after('show_friends_list');
            $table->boolean('searchable')->default(true)->after('appear_in_friends_lists');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['show_friends_list', 'appear_in_friends_lists', 'searchable']);
        });
    }
};
