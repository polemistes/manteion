<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People following an organizer. Organizers are public, so a follow
     * needs no approval.
     */
    public function up(): void
    {
        Schema::create('organizer_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['organizer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_follows');
    }
};
