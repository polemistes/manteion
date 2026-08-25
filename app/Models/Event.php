<?php

namespace App\Models;

use App\Enums\EventVisibility;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $place
 * @property string $description
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property EventVisibility $visibility
 * @property bool $allow_invitee_invites
 * @property bool $allow_invitee_comments
 * @property Carbon|null $searchable_paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'place', 'description', 'starts_at', 'ends_at', 'visibility', 'allow_invitee_invites', 'allow_invitee_comments', 'searchable_paid_at'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'visibility' => EventVisibility::class,
            'allow_invitee_invites' => 'boolean',
            'allow_invitee_comments' => 'boolean',
            'searchable_paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
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

    public function attendeeFor(User $user): ?EventAttendee
    {
        return $this->attendees->firstWhere('user_id', $user->id);
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->visibility === EventVisibility::Searchable) {
            return true;
        }

        if ($this->attendeeFor($user) !== null) {
            return true;
        }

        if ($this->visibility === EventVisibility::Public) {
            return $user->isFollowing($this->owner);
        }

        return false;
    }
}
