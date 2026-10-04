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
     * Push preferences move from one boolean column per kind of notification
     * to a single JSON settings column (categories and their defaults live in
     * App\Enums\NotificationCategory). The old values are dropped rather than
     * carried over, so everyone starts from the new defaults. The Classic/Soft
     * style choice is removed, conversations can be muted, and the stored
     * notifications are cleared because their data shape changes.
     */
    public function up(): void
    {
        DB::table('notifications')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_settings')->nullable()->after('searchable');
            $table->dropColumn([
                'push_notifications_enabled',
                'notify_event_invites',
                'notify_messages',
                'notify_friend_requests',
                'notify_follow_requests',
                'theme_style',
            ]);
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->timestamp('muted_at')->nullable()->after('last_read_at');
        });
    }

    /**
     * Reverse the migrations. The old columns come back with their original
     * defaults; the cleared notifications cannot be restored.
     */
    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn('muted_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_notifications_enabled')->default(true)->after('searchable');
            $table->boolean('notify_event_invites')->default(true)->after('push_notifications_enabled');
            $table->boolean('notify_messages')->default(true)->after('notify_event_invites');
            $table->boolean('notify_friend_requests')->default(true)->after('notify_messages');
            $table->boolean('notify_follow_requests')->default(true)->after('notify_friend_requests');
            $table->string('theme_style')->default('classic')->after('locale');
            $table->dropColumn('notification_settings');
        });
    }
};
