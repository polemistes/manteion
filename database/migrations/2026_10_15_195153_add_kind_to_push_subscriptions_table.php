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
     * A device gets pushes either through its browser (web push) or through
     * the Android app (Firebase Cloud Messaging). For an app device the
     * endpoint column holds its FCM registration token and the key columns
     * stay empty. Every existing row is a browser.
     */
    public function up(): void
    {
        $connection = config('webpush.database_connection');
        $table = config('webpush.table_name');

        Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
            $blueprint->string('kind', 16)->default('webpush')->after('subscribable_id');
        });
    }

    /**
     * Reverse the migrations. App devices go, since a web push row can't
     * hold them.
     */
    public function down(): void
    {
        $connection = config('webpush.database_connection');
        $table = config('webpush.table_name');

        DB::connection($connection)->table($table)->where('kind', '!=', 'webpush')->delete();

        Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('kind');
        });
    }
};
