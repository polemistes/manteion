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
        Schema::create('leaflets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('visibility')->index();
            $table->string('image_path')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->string('link_title')->nullable();
            $table->string('link_description', 500)->nullable();
            $table->string('link_image_url', 2048)->nullable();
            $table->timestamp('link_fetched_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaflets');
    }
};
