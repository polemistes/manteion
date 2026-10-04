<?php

namespace App\Models;

use App\Enums\ConversationType;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property ConversationType $type
 * @property string|null $name
 * @property int|null $creator_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $latest_message_id only when selected by the withLatestMessage scope
 * @property-read int $unread_count only when selected by the listedFor scope
 */
#[Fillable(['type', 'name', 'creator_id'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * How long one "I'm looking at this thread" heartbeat counts. The open
     * thread renews it every ~25 seconds, so a closed laptop or a lost
     * connection stops suppressing pushes within this long.
     */
    public const VIEWER_TTL_SECONDS = 45;

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The newest message, through the `latest_message_id` the
     * withLatestMessage scope selects. Always empty without that scope.
     *
     * @return BelongsTo<Message, $this>
     */
    public function latestMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'latest_message_id');
    }

    /**
     * Select each conversation's newest message id and eager load that
     * message with its sender. One lookup per conversation on the
     * (conversation_id, created_at) index, rather than loading or ranking
     * every message of every conversation to find the last ones.
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    protected function scopeWithLatestMessage(Builder $query): Builder
    {
        return $query
            ->addSelect(['latest_message_id' => Message::query()
                ->select('messages.id')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->orderByDesc('messages.created_at')
                ->orderByDesc('messages.id')
                ->limit(1)])
            ->with('latestMessage.sender');
    }

    /**
     * The user's conversations as their list shows them: each with its
     * newest message (see withLatestMessage) and, as `unread_count`, how
     * many messages others wrote since the user last read it — all of them
     * if they never have, the same rule as the messages badge.
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    protected function scopeListedFor(Builder $query, User $user): Builder
    {
        return $query
            ->select('conversations.*')
            ->join('conversation_participants as viewer', fn (JoinClause $join) => $join
                ->on('viewer.conversation_id', '=', 'conversations.id')
                ->where('viewer.user_id', '=', $user->id))
            ->withLatestMessage()
            ->withCount(['messages as unread_count' => fn (Builder $messages) => $messages
                ->where('messages.user_id', '!=', $user->id)
                // "Never read" as the earliest possible time keeps this one
                // range condition, which the (conversation_id, created_at)
                // index serves.
                ->where('messages.created_at', '>', DB::raw("coalesce(viewer.last_read_at, '1970-01-01 00:00:00')"))]);
    }

    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->participants->firstWhere('user_id', $user->id);
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->participantFor($user) !== null;
    }

    /**
     * Everyone in the conversation except the given user, in the order
     * they joined. For staff looking in from the moderation queue, that is
     * everyone.
     *
     * @return Collection<int, User>
     */
    public function otherParticipants(User $viewer): Collection
    {
        return $this->participants
            ->reject(fn (ConversationParticipant $participant) => $participant->user_id === $viewer->id)
            ->sortBy('id')
            ->map(fn (ConversationParticipant $participant) => $participant->user)
            ->values();
    }

    /**
     * What the conversation is called for the given user: the other
     * person's name in a direct conversation; in a group its name, or else
     * the other members' names.
     */
    public function titleFor(User $viewer): string
    {
        if ($this->type === ConversationType::Group && filled($this->name)) {
            return $this->name;
        }

        $names = $this->otherParticipants($viewer)->pluck('name');

        if ($names->isEmpty()) {
            // Everyone else left, or the other person deleted their account.
            return $this->type === ConversationType::Direct
                ? __('chat.conversation.deleted_account')
                : __('chat.conversation.only_you');
        }

        return $names->join(', ');
    }

    /**
     * Record that the user has the thread open in front of them (or no
     * longer has), so new-message pushes skip them meanwhile.
     */
    public function setViewing(User $user, bool $viewing): void
    {
        if ($viewing) {
            Cache::put($this->viewerCacheKey($user), true, self::VIEWER_TTL_SECONDS);

            return;
        }

        Cache::forget($this->viewerCacheKey($user));
    }

    public function isBeingViewedBy(User $user): bool
    {
        return Cache::has($this->viewerCacheKey($user));
    }

    private function viewerCacheKey(User $user): string
    {
        return "conversation.{$this->id}.viewer.{$user->id}";
    }

    /**
     * Find the existing direct conversation between two users, or start a
     * new one. Shared by user-initiated messaging (`ConversationController`)
     * and staff-initiated messaging from the admin/moderator panels.
     */
    public static function findOrCreateDirect(User $a, User $b): self
    {
        $existing = self::query()
            ->where('type', ConversationType::Direct)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $a->id))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $b->id))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $conversation = self::create([
            'type' => ConversationType::Direct,
            'creator_id' => $a->id,
        ]);

        $conversation->participants()->createMany([
            ['user_id' => $a->id],
            ['user_id' => $b->id],
        ]);

        return $conversation;
    }
}
