<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An event template is what a host fills in for an event, kept to start
     * new events from: everything but the day. Its times are clock times
     * ("19:00", ending "21:00" the same day or some days later), not
     * instants. It is a copy, so it outlives the event it was made from,
     * and its cover is a file of its own. One made for an organizer is
     * shared by the organizer's spokespersons.
     */
    public function up(): void
    {
        Schema::create('event_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('place')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->text('description');
            $table->string('starts_time', 5)->nullable();
            $table->string('ends_time', 5)->nullable();
            $table->unsignedSmallInteger('ends_days_later')->default(0);
            $table->string('timezone', 64)->nullable();
            $table->string('timezone_mode')->default('auto');
            $table->string('visibility')->default('private');
            $table->boolean('allow_invitee_invites')->default(false);
            $table->boolean('allow_invitee_comments')->default(true);
            $table->string('image_path')->nullable();
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->string('image_fit')->nullable();
            $table->timestamps();
        });

        Schema::create('event_template_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_template_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_template_tag');
        Schema::dropIfExists('event_templates');
    }
};
