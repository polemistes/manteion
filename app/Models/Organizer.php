<?php

namespace App\Models;

use App\Enums\SpokespersonRole;
use Database\Factories\OrganizerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A venue or organisation with a page of its own. It hosts events and
 * publishes leaflets through its spokespersons, and people follow it to
 * hear of its public events. What a spokesperson does for it is shown with
 * their name.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string|null $description
 * @property string|null $avatar_path
 * @property int|null $created_by
 * @property-read string|null $avatar_url
 */
#[Fillable(['name', 'handle', 'description', 'avatar_path', 'created_by'])]
class Organizer extends Model
{
    /** @use HasFactory<OrganizerFactory> */
    use HasFactory;

    /**
     * Everyone with a row, invitations included.
     *
     * @return BelongsToMany<User, $this, OrganizerSpokesperson>
     */
    public function spokespersons(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizer_spokespersons')
            ->using(OrganizerSpokesperson::class)
            ->withPivot(['id', 'role', 'listed', 'invited_by_user_id', 'accepted_at'])
            ->withTimestamps();
    }

    /**
     * Those who accepted: the people who may act for the organizer.
     *
     * @return BelongsToMany<User, $this, OrganizerSpokesperson>
     */
    public function activeSpokespersons(): BelongsToMany
    {
        return $this->spokespersons()->wherePivotNotNull('accepted_at');
    }

    /**
     * @return HasMany<OrganizerSpokesperson, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizerSpokesperson::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizer_follows')->withTimestamps();
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Leaflet, $this>
     */
    public function leaflets(): HasMany
    {
        return $this->hasMany(Leaflet::class);
    }

    public function membershipOf(User $user): ?OrganizerSpokesperson
    {
        return $this->memberships()->where('user_id', $user->id)->first();
    }

    public function hasSpokesperson(User $user): bool
    {
        return $user->organizerIds()->contains($this->id);
    }

    public function isMainSpokesperson(User $user): bool
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('role', SpokespersonRole::Main)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    public function mainSpokesperson(): ?User
    {
        return $this->activeSpokespersons()->wherePivot('role', SpokespersonRole::Main->value)->first();
    }

    public function isFollowedBy(User $user): bool
    {
        return $this->followers()->whereKey($user->id)->exists();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path === null ? null : Storage::disk('public')->url($this->avatar_path);
    }

    /**
     * The organizer as pages carry it beside an event or leaflet.
     *
     * @return array{id: int, name: string, handle: string, avatar_url: string|null}
     */
    public function summary(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'handle' => $this->handle, 'avatar_url' => $this->avatar_url];
    }
}
