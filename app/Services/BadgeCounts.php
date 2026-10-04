<?php

namespace App\Services;

use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The numbers behind the app's badges, shared by the page props, the
 * `badges.updated` broadcast, GET /badges and push payloads so they always
 * agree. One cheap count query each.
 */
class BadgeCounts
{
    /**
     * @return array{messages: int, activity: int, requests: int}
     */
    public static function for(User $user): array
    {
        return [
            'messages' => self::unreadConversations($user),
            'activity' => $user->unreadNotifications()->count(),
            'requests' => self::pendingIncomingRequests($user),
        ];
    }

    /**
     * The counts for someone with nothing waiting (a guest, a deleted user).
     *
     * @return array{messages: int, activity: int, requests: int}
     */
    public static function none(): array
    {
        return ['messages' => 0, 'activity' => 0, 'requests' => 0];
    }

    /**
     * Conversations the user has not muted that got a message from someone
     * else since the user last read them, or at all if they never have.
     * Split in two so the "since" case can range-scan the messages index on
     * (conversation_id, created_at) instead of walking the whole history.
     */
    private static function unreadConversations(User $user): int
    {
        $messagesFromOthers = fn (): Builder => Message::query()
            ->whereColumn('messages.conversation_id', 'conversation_participants.conversation_id')
            ->where('messages.user_id', '!=', $user->id);

        return ConversationParticipant::query()
            ->whereBelongsTo($user)
            ->whereNull('conversation_participants.muted_at')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $neverRead) => $neverRead
                    ->whereNull('conversation_participants.last_read_at')
                    ->whereExists($messagesFromOthers()))
                ->orWhereExists($messagesFromOthers()
                    ->whereColumn('messages.created_at', '>', 'conversation_participants.last_read_at')))
            ->count();
    }

    /**
     * Friend requests and follow requests other people sent the user that
     * still await an answer.
     */
    private static function pendingIncomingRequests(User $user): int
    {
        return $user->pendingFriendRequests()->count()
            + Follow::query()->where('followed_id', $user->id)->pending()->count();
    }
}
