<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each push subscription is one device. Its browser's user agent is kept
     * so the notification settings can list the devices by name ("Chrome on
     * Android") and let the user remove one. Rows from before this have none.
     */
    public function up(): void
    {
        $connection = config('webpush.database_connection');
        $table = config('webpush.table_name');

        Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
            $blueprint->string('user_agent', 512)->nullable()->after('content_encoding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('webpush.database_connection');
        $table = config('webpush.table_name');

        Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('user_agent');
        });
    }
};
