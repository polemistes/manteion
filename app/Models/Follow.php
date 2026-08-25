<?php

namespace App\Models;

use App\Enums\FollowStatus;
use Database\Factories\FollowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $follower_id
 * @property int $followed_id
 * @property FollowStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['follower_id', 'followed_id', 'status'])]
class Follow extends Model
{
    /** @use HasFactory<FollowFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => FollowStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function followed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followed_id');
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    protected function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', FollowStatus::Accepted);
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    protected function scopePending(Builder $query): Builder
    {
        return $query->where('status', FollowStatus::Pending);
    }
}
