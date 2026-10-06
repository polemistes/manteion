<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An event or leaflet hosted by an organizer carries its id beside the
     * spokesperson who made it (user_id stays the person). A comment made
     * as the organizer carries it too; if the organizer goes, the comment
     * stays as the person's.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });
        Schema::table('leaflets', function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });
        Schema::table('event_comments', function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
        });
        Schema::table('leaflets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
        });
    }
};
