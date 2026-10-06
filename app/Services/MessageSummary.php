<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The message shapes the chat sends: `for()` is what the conversation page
 * renders for one viewer (initial load, older and newer pages, a sent
 * message's response); `broadcast()` is the viewer-agnostic version sent
 * over the conversation channel, from which each client derives `for()`'s
 * viewer-specific fields itself.
 */
class MessageSummary
{
    /**
     * How much of a message the conversation list and in-app toasts show.
     */
    public const PREVIEW_LENGTH = 120;

    /**
     * @return array{id: int, body: string|null, is_deleted: bool, created_at: string, sender: array{id: int, name: string, username: string, avatar_url: string|null}, is_mine: bool, can_delete: bool, can_report: bool, reactions: array<int, array{emoji: string, count: int, reacted: bool}>, seen_by: list<array{id: int, name: string, avatar_url: string|null}>}
     */
    public static function for(Message $message, User $viewer): array
    {
        return [
            'id' => $message->id,
            'body' => $message->visibleBody(),
            'is_deleted' => $message->is_deleted,
            'created_at' => $message->created_at->toIso8601String(),
            'sender' => self::sender($message),
            'is_mine' => $message->user_id === $viewer->id,
            'can_delete' => ! $message->is_deleted && Gate::forUser($viewer)->allows('delete', $message),
            'can_report' => ! $message->is_deleted && $message->user_id !== $viewer->id,
            'reactions' => Reaction::summarize($message->reactions, $viewer),
            'seen_by' => self::seenBy($message),
        ];
    }

    /**
     * The same message for everyone in the conversation at once: who
     * reacted is listed instead of whether the viewer did.
     *
     * @return array{id: int, conversation_id: int, body: string|null, is_deleted: bool, created_at: string, sender: array{id: int, name: string, username: string, avatar_url: string|null}, reactions: array<int, array{emoji: string, count: int, user_ids: array<int, int>}>, seen_by: list<array{id: int, name: string, avatar_url: string|null}>}
     */
    public static function broadcast(Message $message): array
    {
        // In Reaction::summarize's order (most used first, ties in the
        // order they first appeared), so a live update lines up with what
        // a page load shows.
        $reactions = $message->reactions
            ->sortBy('id')
            ->groupBy('emoji')
            ->map(fn (EloquentCollection $group, string $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'user_ids' => $group->map(fn (Reaction $reaction) => $reaction->user_id)->values()->all(),
                'first_id' => $group->min('id'),
            ])
            ->sortBy([['count', 'desc'], ['first_id', 'asc']])
            ->map(fn (array $summary) => ['emoji' => $summary['emoji'], 'count' => $summary['count'], 'user_ids' => $summary['user_ids']])
            ->values()
            ->all();

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->visibleBody(),
            'is_deleted' => $message->is_deleted,
            'created_at' => $message->created_at->toIso8601String(),
            'sender' => self::sender($message),
            'reactions' => $reactions,
            'seen_by' => self::seenBy($message),
        ];
    }

    /**
     * Who marked the message as seen on purpose, oldest first.
     *
     * @return list<array{id: int, name: string, avatar_url: string|null}>
     */
    private static function seenBy(Message $message): array
    {
        return array_values($message->seenBy
            ->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'avatar_url' => $person->avatar_url,
            ])
            ->all());
    }

    /**
     * The message text on one line and cut to the given length, for lists,
     * toasts and pushes. Null for a wiped delete.
     */
    public static function preview(Message $message, int $length = self::PREVIEW_LENGTH): ?string
    {
        $body = $message->visibleBody();

        return $body === null ? null : Str::limit(Str::squish($body), $length, '…');
    }

    /**
     * @return array{id: int, name: string, username: string, avatar_url: string|null}
     */
    private static function sender(Message $message): array
    {
        return [
            'id' => $message->sender->id,
            'name' => $message->sender->name,
            'username' => $message->sender->username,
            'avatar_url' => $message->sender->avatar_url,
        ];
    }
}
