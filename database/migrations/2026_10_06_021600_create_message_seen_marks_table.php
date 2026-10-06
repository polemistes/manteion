<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A reader's deliberate "I have seen this" on someone else's message,
     * shown under it to everyone in the conversation, whatever the privacy
     * switches say (the automatic "Seen" is governed by those).
     */
    public function up(): void
    {
        Schema::create('message_seen_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_seen_marks');
    }
};
