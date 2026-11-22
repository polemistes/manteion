<?php

namespace App\Models\Concerns;

use App\Models\Picture;
use App\Services\ImageProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

/**
 * For what can carry pictures: a chat message, a leaflet, an event comment.
 * Deleting one through its model deletes its pictures' files first (the
 * rows go with it by foreign key).
 *
 * @mixin Model
 */
trait HasPictures
{
    public static function bootHasPictures(): void
    {
        static::deleting(fn (self $owner) => $owner->deletePictures());
    }

    /**
     * In the order they were added.
     *
     * @return HasMany<Picture, $this>
     */
    public function pictures(): HasMany
    {
        return $this->hasMany(Picture::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Stores uploads as this owner's next pictures, each with a smaller
     * copy for grids, under `$directory` on the private disk.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function addPictures(array $files, string $directory): void
    {
        if ($files === []) {
            return;
        }

        $processor = app(ImageProcessor::class);
        $last = $this->pictures()->max('position');
        $position = $last === null ? 0 : (int) $last + 1;

        foreach (array_values($files) as $file) {
            $this->pictures()->create([...$processor->storePicture($file, $directory), 'position' => $position++]);
        }

        $this->unsetRelation('pictures');
    }

    /**
     * Deletes the pictures, files and rows.
     */
    public function deletePictures(): void
    {
        $this->pictures()->get()->each->delete();
        $this->unsetRelation('pictures');
    }

    /**
     * @return list<array{id: int, url: string, thumb_url: string, width: int|null, height: int|null}>
     */
    public function picturesPresentation(): array
    {
        return array_values($this->pictures->map(fn (Picture $picture) => $picture->presentation())->all());
    }
}
