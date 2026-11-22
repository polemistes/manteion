<?php

namespace App\Models;

use App\Enums\EventTimeZoneMode;
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
use Illuminate\Support\Collection as SupportCollection;

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
 * @property string|null $timezone
 * @property EventTimeZoneMode $timezone_mode
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
#[Fillable(['user_id', 'organizer_id', 'name', 'place', 'latitude', 'longitude', 'city', 'country', 'country_code', 'description', 'starts_at', 'ends_at', 'timezone', 'timezone_mode', 'visibility', 'allow_invitee_invites', 'allow_invitee_comments', 'searchable_paid_at', 'followers_notified_at', 'is_deleted'])]
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
            'timezone_mode' => EventTimeZoneMode::class,
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
     * The guests (everyone on the guest list, whatever they answered) who
     * are this person's friends: the ones a host can keep as a group and
     * invite again.
     *
     * @return SupportCollection<int, int>
     */
    public function friendGuestIdsOf(User $user): SupportCollection
    {
        return $this->attendees->pluck('user_id')
            ->intersect($user->friendIds())
            ->reject(fn (int $id) => $id === $user->id)
            ->values();
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
     * The time zone the event's times were typed in, and whose days are
     * its days: the one at its pin, one its host chose, or (without a pin,
     * or for an event with no local time) its host's own.
     */
    public function localTimeZone(): string
    {
        return $this->timezone ?? (string) config('app.display_timezone');
    }

    /**
     * The time zone whose clock is shown beside each reader's own, or
     * null for an event that has no local time (an online one).
     */
    public function shownLocalTimeZone(): ?string
    {
        return $this->timezone_mode === EventTimeZoneMode::None ? null : $this->localTimeZone();
    }

    /**
     * When the event is over: its end, or for one without an end time, the
     * end of the day it starts on (where it happens), so a pub quiz at
     * 19:00 can still be answered at 20:00.
     */
    public function endsAtOrEndOfDay(): CarbonImmutable
    {
        if ($this->ends_at !== null) {
            return CarbonImmutable::instance($this->ends_at);
        }

        return CarbonImmutable::instance($this->starts_at)
            ->setTimezone($this->localTimeZone())
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
