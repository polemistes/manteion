<?php

namespace App\Models;

use App\Enums\EventVisibility;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $organizer_id
 * @property string $name
 * @property string|null $place
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $city
 * @property string|null $country
 * @property string $description
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property EventVisibility $visibility
 * @property bool $allow_invitee_invites
 * @property bool $allow_invitee_comments
 * @property Carbon|null $searchable_paid_at
 * @property Carbon|null $followers_notified_at
 * @property bool $is_deleted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $attending_count
 * @property-read int $interested_count
 */
#[Fillable(['user_id', 'organizer_id', 'name', 'place', 'latitude', 'longitude', 'city', 'country', 'country_code', 'description', 'starts_at', 'ends_at', 'visibility', 'allow_invitee_invites', 'allow_invitee_comments', 'searchable_paid_at', 'followers_notified_at', 'is_deleted'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'visibility' => EventVisibility::class,
            'allow_invitee_invites' => 'boolean',
            'allow_invitee_comments' => 'boolean',
            'searchable_paid_at' => 'datetime',
            'followers_notified_at' => 'datetime',
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * The person who made the event: the host, or the spokesperson who made
     * it for the organizer.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The organizer hosting the event, if a spokesperson made it for one.
     *
     * @return BelongsTo<Organizer, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * Whether this person speaks for the event: made it themselves, or is
     * a spokesperson of the organizer hosting it.
     */
    public function isHostedBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->organizer_id !== null && $user->organizerIds()->contains($this->organizer_id);
    }

    /**
     * The name the event is hosted under.
     */
    public function hostName(): string
    {
        return $this->organizer->name ?? $this->owner->name;
    }

    /**
     * The people who speak for the event: its maker, or the organizer's
     * spokespersons.
     *
     * @return Collection<int, User>
     */
    public function hosts(): Collection
    {
        return $this->organizer === null
            ? $this->owner->newCollection([$this->owner])
            : $this->organizer->activeSpokespersons()->get();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('position');
    }

    /**
     * @return HasMany<EventImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class)->orderBy('position');
    }

    /**
     * @return HasMany<EventAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    /**
     * @return HasMany<EventComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(EventComment::class);
    }

    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * When the event is over: its end, or for one without an end time, the
     * end of the day it starts on (in the display time zone), so a pub
     * quiz at 19:00 can still be answered at 20:00.
     */
    public function endsAtOrEndOfDay(): CarbonImmutable
    {
        if ($this->ends_at !== null) {
            return CarbonImmutable::instance($this->ends_at);
        }

        return CarbonImmutable::instance($this->starts_at)
            ->setTimezone((string) config('app.display_timezone'))
            ->addDay()
            ->startOfDay()
            ->utc();
    }

    public function isOver(): bool
    {
        return $this->endsAtOrEndOfDay()->isPast();
    }

    public function attendeeFor(User $user): ?EventAttendee
    {
        return $this->attendees->firstWhere('user_id', $user->id);
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->isHostedBy($user)) {
            return true;
        }

        if ($this->visibility === EventVisibility::Searchable) {
            return true;
        }

        if ($this->attendeeFor($user) !== null) {
            return true;
        }

        if ($this->visibility === EventVisibility::Public) {
            // An organizer's public events reach its followers; a person's,
            // the people following them.
            return $this->organizer_id !== null
                ? $user->followedOrganizerIds()->contains($this->organizer_id)
                : $user->isFollowing($this->owner);
        }

        return false;
    }
}
