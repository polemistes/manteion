<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * creator_id previously cascaded, so deleting whoever happened to start
     * a conversation deleted the whole conversation — including every other
     * participant's messages. creator_id is only ever used as provenance
     * (never for authorization or display), so it's safe to just null it
     * out instead.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->unsignedBigInteger('creator_id')->nullable()->change();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->unsignedBigInteger('creator_id')->nullable(false)->change();
            $table->foreign('creator_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
