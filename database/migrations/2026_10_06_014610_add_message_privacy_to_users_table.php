<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two privacy switches for chats: whether others see you typing (on
     * unless turned off) and whether they see that you have read their
     * messages (off unless turned on).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('share_typing')->default(true)->after('searchable');
            $table->boolean('share_read_receipts')->default(false)->after('share_typing');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['share_typing', 'share_read_receipts']);
        });
    }
};
