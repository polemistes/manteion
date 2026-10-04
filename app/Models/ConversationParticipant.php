<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ConversationParticipantFactory;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property Carbon|null $last_read_at
 * @property Carbon|null $muted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['conversation_id', 'user_id', 'last_read_at', 'muted_at'])]
class ConversationParticipant extends Model
{
    /** @use HasFactory<ConversationParticipantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'muted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isMuted(): bool
    {
        return $this->muted_at !== null;
    }

    /**
     * Whether someone else has written since this participant last read
     * the conversation (or at all, if they never have) — the rule the
     * messages badge counts by.
     */
    public function hasUnreadMessages(): bool
    {
        return Message::query()
            ->where('conversation_id', $this->conversation_id)
            ->where('user_id', '!=', $this->user_id)
            ->when($this->last_read_at, fn (Builder $query, CarbonInterface $readAt) => $query->where('created_at', '>', $readAt))
            ->exists();
    }

    public function markRead(): void
    {
        $this->update(['last_read_at' => now()]);
    }

    /**
     * The own channels of a conversation's participants as they are now, for
     * what concerns its messages: someone who left stops hearing at once,
     * whatever they still have open (a subscription to the conversation's
     * channel lasts as long as the socket does).
     *
     * @return list<PrivateChannel>
     */
    public static function channelsFor(int $conversationId, ?int $exceptUserId = null): array
    {
        return array_values(self::query()
            ->where('conversation_id', $conversationId)
            ->when($exceptUserId !== null, fn (Builder $query) => $query->where('user_id', '!=', $exceptUserId))
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn (int $userId) => new PrivateChannel("App.Models.User.{$userId}"))
            ->all());
    }
}
