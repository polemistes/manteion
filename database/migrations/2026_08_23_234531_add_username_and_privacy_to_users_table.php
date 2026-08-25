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
            // SQLite's ALTER TABLE ADD COLUMN rejects a NOT NULL column with no
            // default, so this starts nullable and is tightened below — the table
            // is always empty at this point in a fresh install, so nothing is lost.
            $table->string('username', 32)->nullable()->unique()->after('name');
            $table->boolean('allow_public_follow')->default(true)->after('password');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'allow_public_follow']);
        });
    }
};
