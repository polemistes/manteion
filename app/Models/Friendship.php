<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $requester_id
 * @property int $addressee_id
 * @property FriendshipStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['requester_id', 'addressee_id', 'status'])]
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /**
     * How this friendship looks from one user's side. A declined request is
     * reported as still sent to its requester and as nothing at all to the
     * addressee, so declining is never revealed.
     */
    public function statusFor(User $viewer): string
    {
        return match (true) {
            $this->status === FriendshipStatus::Accepted => 'friends',
            $this->requester_id === $viewer->id => 'request_sent',
            $this->status === FriendshipStatus::Declined => 'none',
            default => 'request_received',
        };
    }

    /**
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    protected function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', FriendshipStatus::Accepted);
    }

    /**
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    protected function scopePending(Builder $query): Builder
    {
        return $query->where('status', FriendshipStatus::Pending);
    }
}
