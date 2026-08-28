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
            $table->boolean('push_notifications_enabled')->default(true)->after('searchable');
            $table->boolean('notify_event_invites')->default(true)->after('push_notifications_enabled');
            $table->boolean('notify_messages')->default(true)->after('notify_event_invites');
            $table->boolean('notify_friend_requests')->default(true)->after('notify_messages');
            $table->boolean('notify_follow_requests')->default(true)->after('notify_friend_requests');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'push_notifications_enabled',
                'notify_event_invites',
                'notify_messages',
                'notify_friend_requests',
                'notify_follow_requests',
            ]);
        });
    }
};
