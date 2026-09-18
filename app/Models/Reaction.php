<?php

namespace App\Models;

use Database\Factories\ReactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An emoji a user attached to a leaflet, a leaflet response or a message.
 *
 * @property int $id
 * @property int $user_id
 * @property string $reactable_type
 * @property int $reactable_id
 * @property string $emoji
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'reactable_type', 'reactable_id', 'emoji'])]
class Reaction extends Model
{
    /** @use HasFactory<ReactionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The reactions on one item as the page shows them: each emoji once,
     * with how many people used it and whether the viewer is among them.
     * Most-used first, ties in the order they first appeared.
     *
     * @param  EloquentCollection<int, Reaction>  $reactions
     * @return array<int, array{emoji: string, count: int, reacted: bool}>
     */
    public static function summarize(EloquentCollection $reactions, User $viewer): array
    {
        return $reactions
            ->groupBy('emoji')
            ->map(fn (EloquentCollection $group, string $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'reacted' => $group->contains('user_id', $viewer->id),
                'first_id' => $group->min('id'),
            ])
            ->sortBy([['count', 'desc'], ['first_id', 'asc']])
            ->map(fn (array $summary) => ['emoji' => $summary['emoji'], 'count' => $summary['count'], 'reacted' => $summary['reacted']])
            ->values()
            ->all();
    }
}
