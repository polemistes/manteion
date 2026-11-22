<?php

namespace App\Models;

use App\Services\ImageProcessor;
use Database\Factories\PictureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One of the pictures on a chat message, a leaflet or an event comment
 * (exactly one of the three keys is set). Its files live on the private
 * disk and reach a page only through PictureController, which checks that
 * the viewer may see what the picture is on.
 *
 * Deleting a picture through the model deletes its files with it. A row
 * that the database deletes with its owner (a cascade) does not: whoever
 * deletes owners in bulk calls forgetFiles() first.
 *
 * @property int $id
 * @property int|null $message_id
 * @property int|null $leaflet_id
 * @property int|null $event_comment_id
 * @property string $path
 * @property string|null $thumb_path
 * @property int|null $width
 * @property int|null $height
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['message_id', 'leaflet_id', 'event_comment_id', 'path', 'thumb_path', 'width', 'height', 'position'])]
class Picture extends Model
{
    /** @use HasFactory<PictureFactory> */
    use HasFactory;

    /** How many pictures one message, leaflet or comment may carry. */
    public const MAX_PER_POST = 10;

    protected static function booted(): void
    {
        static::deleting(fn (Picture $picture) => $picture->deleteFiles());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<Leaflet, $this>
     */
    public function leaflet(): BelongsTo
    {
        return $this->belongsTo(Leaflet::class);
    }

    /**
     * @return BelongsTo<EventComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(EventComment::class, 'event_comment_id');
    }

    /**
     * What a page needs to frame the picture before it loads: its address
     * at full size, a smaller copy for a grid (the same address when the
     * picture is small itself), and its size. The addresses name the
     * files, so a browser may keep them for good.
     *
     * @return array{id: int, url: string, thumb_url: string, width: int|null, height: int|null}
     */
    public function presentation(): array
    {
        $url = route('pictures.show', [$this->id, basename($this->path)]);

        return [
            'id' => $this->id,
            'url' => $url,
            'thumb_url' => $this->thumb_path === null ? $url : route('pictures.show', [$this->id, basename($this->thumb_path)]),
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * The files behind the picture.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return array_values(array_filter([$this->path, $this->thumb_path]));
    }

    public function deleteFiles(): void
    {
        Storage::disk(ImageProcessor::PRIVATE_DISK)->delete($this->files());
    }

    /**
     * Deletes the files of the pictures a query finds, and leaves the rows
     * to the database: for owners about to be deleted in bulk.
     *
     * @param  Builder<Picture>  $pictures
     */
    public static function forgetFiles(Builder $pictures): void
    {
        $files = $pictures->get(['id', 'path', 'thumb_path'])->flatMap(fn (Picture $picture) => $picture->files())->all();

        if ($files !== []) {
            Storage::disk(ImageProcessor::PRIVATE_DISK)->delete($files);
        }
    }
}
