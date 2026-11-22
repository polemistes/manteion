<?php

namespace App\Models;

use App\Enums\EventTimeZoneMode;
use App\Enums\EventVisibility;
use App\Services\ImageProcessor;
use Database\Factories\EventTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * What a host fills in for an event, kept to start new events from:
 * everything but the day. The times are clock times in the template's own
 * zone ("19:00", ending "21:00" `ends_days_later` days after the start),
 * and may be left out. A template is a copy: it does not change with the
 * event it was made from, and its cover is a file of its own on the
 * private disk, reached through EventTemplateController::cover.
 *
 * Deleting a template through the model deletes its cover with it. A row
 * the database deletes with its maker or organizer (a cascade) does not:
 * User::deleteUploadedFiles() and Organizer::deleteUploadedFiles() do.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $organizer_id
 * @property string $name
 * @property string|null $place
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $city
 * @property string|null $country
 * @property string|null $country_code
 * @property string $description
 * @property string|null $starts_time
 * @property string|null $ends_time
 * @property int $ends_days_later
 * @property string|null $timezone
 * @property EventTimeZoneMode $timezone_mode
 * @property EventVisibility $visibility
 * @property bool $allow_invitee_invites
 * @property bool $allow_invitee_comments
 * @property string|null $image_path
 * @property int|null $image_width
 * @property int|null $image_height
 * @property 'cover'|'contain'|null $image_fit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'organizer_id', 'name', 'place', 'latitude', 'longitude', 'city', 'country', 'country_code', 'description', 'starts_time', 'ends_time', 'ends_days_later', 'timezone', 'timezone_mode', 'visibility', 'allow_invitee_invites', 'allow_invitee_comments', 'image_path', 'image_width', 'image_height', 'image_fit'])]
class EventTemplate extends Model
{
    /** @use HasFactory<EventTemplateFactory> */
    use HasFactory;

    /** The most days after its start a template's event may end. */
    public const MAX_DAYS_LATER = 60;

    protected static function booted(): void
    {
        static::deleting(fn (EventTemplate $template) => $template->deleteCoverFile());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'ends_days_later' => 'integer',
            'timezone_mode' => EventTimeZoneMode::class,
            'visibility' => EventVisibility::class,
            'allow_invitee_invites' => 'boolean',
            'allow_invitee_comments' => 'boolean',
            'image_width' => 'integer',
            'image_height' => 'integer',
        ];
    }

    /**
     * The person who made the template.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The organizer its events are hosted by, if it was made for one.
     *
     * @return BelongsTo<Organizer, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps()->orderBy('position');
    }

    /**
     * The templates this person may use and change: their own, and those
     * of the organizers they speak for.
     *
     * @param  Builder<EventTemplate>  $query
     * @return Builder<EventTemplate>
     */
    protected function scopeUsableBy(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhereIn('organizer_id', $user->organizerIds()));
    }

    /**
     * Whether this person may use and change the template: they made it,
     * or speak for the organizer it belongs to (as with its events, see
     * Event::isHostedBy()).
     */
    public function isUsableBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->organizer_id !== null && $user->organizerIds()->contains($this->organizer_id);
    }

    /**
     * The zone the template's clock times are in.
     */
    public function localTimeZone(): string
    {
        return $this->timezone ?? (string) config('app.display_timezone');
    }

    /**
     * The cover as a page frames it (the shape of EventImage::presentation(),
     * with the template's id), or null without one. The address only opens
     * for someone who may use the template.
     *
     * @return array{id: int, url: string, width: int|null, height: int|null, shape: 'photo'|'poster', fit: 'cover'|'contain'|null}|null
     */
    public function cover(): ?array
    {
        if ($this->image_path === null) {
            return null;
        }

        return [
            'id' => $this->id,
            'url' => route('event-templates.cover', [$this->id, basename($this->image_path)]),
            'width' => $this->image_width,
            'height' => $this->image_height,
            'shape' => EventImage::shapeOf($this->image_width, $this->image_height, $this->image_fit),
            'fit' => $this->image_fit,
        ];
    }

    /**
     * What a list needs to show the template.
     *
     * @return array{id: int, name: string, place: string|null, city: string|null, country: string|null, country_code: string|null, starts_time: string|null, ends_time: string|null, organizer: array{id: int, name: string, handle: string, avatar_url: string|null}|null, cover: array{id: int, url: string, width: int|null, height: int|null, shape: 'photo'|'poster', fit: 'cover'|'contain'|null}|null}
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'place' => $this->place,
            'city' => $this->city,
            'country' => $this->country,
            'country_code' => $this->country_code === null ? null : strtoupper($this->country_code),
            'starts_time' => $this->starts_time,
            'ends_time' => $this->starts_time === null ? null : $this->ends_time,
            'organizer' => $this->organizer?->summary(),
            'cover' => $this->cover(),
        ];
    }

    /**
     * Removes the cover's file; the columns are the caller's to clear.
     */
    public function deleteCoverFile(): void
    {
        if ($this->image_path !== null) {
            Storage::disk(ImageProcessor::PRIVATE_DISK)->delete($this->image_path);
        }
    }
}
