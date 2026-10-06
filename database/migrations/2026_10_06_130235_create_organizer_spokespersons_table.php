<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who may act for an organizer. One of them is the main spokesperson,
     * who invites and removes the others and may hand the role on. A row
     * without accepted_at is an invitation not yet answered. `listed` is
     * whether the person is shown on the organizer's page.
     */
    public function up(): void
    {
        Schema::create('organizer_spokespersons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('spokesperson');
            $table->boolean('listed')->default(true);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['organizer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_spokespersons');
    }
};
