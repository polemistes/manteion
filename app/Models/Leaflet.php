<?php

namespace App\Models;

use App\Enums\LeafletVisibility;
use Database\Factories\LeafletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A short message with one optional image or one optional link.
 *
 * @property int $id
 * @property int $user_id
 * @property string $body
 * @property LeafletVisibility $visibility
 * @property string|null $image_path
 * @property string|null $link_url
 * @property string|null $link_title
 * @property string|null $link_description
 * @property string|null $link_image_url
 * @property Carbon|null $link_fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'body', 'visibility', 'image_path', 'link_url', 'link_title', 'link_description', 'link_image_url', 'link_fetched_at'])]
class Leaflet extends Model
{
    /** @use HasFactory<LeafletFactory> */
    use HasFactory;

    /**
     * Reactions hang off a polymorphic relation, which no foreign key
     * cascades, so they go when the leaflet (and its responses) go.
     */
    protected static function booted(): void
    {
        static::deleting(function (Leaflet $leaflet) {
            $leaflet->responses->each->delete();
            $leaflet->reactions()->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'visibility' => LeafletVisibility::class,
            'link_fetched_at' => 'datetime',
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
     * @return HasMany<LeafletResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(LeafletResponse::class)->orderBy('id');
    }

    /**
     * @return MorphMany<Reaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return match ($this->visibility) {
            LeafletVisibility::Official => true,
            LeafletVisibility::Friends => $user->isFriendsWith($this->owner),
            LeafletVisibility::FriendsAndFollowers => $user->isFriendsWith($this->owner) || $user->isFollowing($this->owner),
            LeafletVisibility::Private => false,
        };
    }
}
