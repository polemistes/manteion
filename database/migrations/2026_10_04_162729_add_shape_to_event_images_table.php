<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Event images remember their size, so a page can tell a photo (cropped to
 * fill its frame) from a poster (always shown whole) when it renders,
 * without measuring the image in the browser. `fit` is the uploader's own
 * choice; null means "decide from the shape".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_images', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('position');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->string('fit', 8)->nullable()->after('height');
        });

        $disk = Storage::disk('public');

        DB::table('event_images')->orderBy('id')->each(function (object $image) use ($disk) {
            if (! $disk->exists($image->path)) {
                return;
            }

            $size = @getimagesize($disk->path($image->path));

            if ($size === false) {
                return;
            }

            DB::table('event_images')->where('id', $image->id)->update([
                'width' => $size[0],
                'height' => $size[1],
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('event_images', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'fit']);
        });
    }
};
