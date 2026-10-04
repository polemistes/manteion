<?php

namespace App\Models;

use Database\Factories\EventImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $event_id
 * @property string $path
 * @property int $position
 * @property int|null $width
 * @property int|null $height
 * @property 'cover'|'contain'|null $fit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'path', 'position', 'width', 'height', 'fit'])]
class EventImage extends Model
{
    /** @use HasFactory<EventImageFactory> */
    use HasFactory;

    /** Images at least this much wider than tall are photos; narrower ones are posters. */
    public const PHOTO_MIN_ASPECT = 1.25;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * "photo" may be cropped to fill its frame; a "poster" is always shown
     * whole. The uploader's choice wins; otherwise the shape decides, and an
     * image of unknown size counts as a photo.
     *
     * @return 'photo'|'poster'
     */
    public function shape(): string
    {
        if ($this->fit !== null) {
            return $this->fit === 'contain' ? 'poster' : 'photo';
        }

        if ($this->width === null || $this->height === null || $this->height === 0) {
            return 'photo';
        }

        return $this->width / $this->height >= self::PHOTO_MIN_ASPECT ? 'photo' : 'poster';
    }

    /**
     * What a page needs to frame the image without measuring it.
     *
     * @return array{id: int, url: string, width: int|null, height: int|null, shape: 'photo'|'poster', fit: 'cover'|'contain'|null}
     */
    public function presentation(): array
    {
        return [
            'id' => $this->id,
            'url' => Storage::disk('public')->url($this->path),
            'width' => $this->width,
            'height' => $this->height,
            'shape' => $this->shape(),
            'fit' => $this->fit,
        ];
    }
}
