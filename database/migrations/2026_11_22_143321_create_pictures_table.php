<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A message and a leaflet could carry one picture, kept in columns of
 * their own; an event comment none. They can now carry several, kept in
 * one table. A picture belongs to exactly one of the three: three foreign
 * keys rather than a polymorphic pair, so its row goes with its owner
 * whichever way the owner is deleted (files are another matter, see
 * App\Models\Picture). The pictures there are move over, files untouched.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pictures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('leaflet_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('event_comment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('path');
            // A smaller copy for grids; null when the picture is small itself.
            $table->string('thumb_path')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });

        DB::table('messages')->whereNotNull('image_path')->orderBy('id')->each(function (object $message): void {
            DB::table('pictures')->insert([
                'message_id' => $message->id,
                'path' => $message->image_path,
                'width' => $message->image_width,
                'height' => $message->image_height,
                'created_at' => $message->created_at,
                'updated_at' => $message->created_at,
            ]);
        });

        DB::table('leaflets')->whereNotNull('image_path')->orderBy('id')->each(function (object $leaflet): void {
            // A leaflet's picture was stored without its size.
            $size = $this->sizeOf($leaflet->image_path);

            DB::table('pictures')->insert([
                'leaflet_id' => $leaflet->id,
                'path' => $leaflet->image_path,
                'width' => $size[0] ?? null,
                'height' => $size[1] ?? null,
                'created_at' => $leaflet->created_at,
                'updated_at' => $leaflet->created_at,
            ]);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_width', 'image_height']);
        });

        Schema::table('leaflets', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    /**
     * Reverse the migrations. Only the first picture of each message and
     * leaflet fits back; the files of the others are left where they are.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('body');
            $table->unsignedSmallInteger('image_width')->nullable()->after('image_path');
            $table->unsignedSmallInteger('image_height')->nullable()->after('image_width');
        });

        Schema::table('leaflets', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('visibility');
        });

        DB::table('pictures')->orderByDesc('position')->orderByDesc('id')->each(function (object $picture): void {
            if ($picture->message_id !== null) {
                DB::table('messages')->where('id', $picture->message_id)->update([
                    'image_path' => $picture->path,
                    'image_width' => $picture->width,
                    'image_height' => $picture->height,
                ]);
            } elseif ($picture->leaflet_id !== null) {
                DB::table('leaflets')->where('id', $picture->leaflet_id)->update(['image_path' => $picture->path]);
            }
        });

        Schema::dropIfExists('pictures');
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function sizeOf(string $path): ?array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return null;
        }

        $size = @getimagesize($disk->path($path));

        return $size === false ? null : [$size[0], $size[1]];
    }
};
