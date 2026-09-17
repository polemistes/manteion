<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The message shape the conversation page renders, shared by the initial
 * page load, its polling refresh, and the "load older" request so all three
 * agree.
 */
class MessageSummary
{
    /**
     * @return array{id: int, body: string|null, is_deleted: bool, created_at: string, sender: array{id: int, name: string, username: string, avatar_url: string|null}, is_mine: bool, can_delete: bool, can_report: bool}
     */
    public static function for(Message $message, User $viewer): array
    {
        return [
            'id' => $message->id,
            // A wiped, non-placeholder delete keeps sending null (the
            // original text is never sent once deleted); a moderator
            // placeholder is meant to be shown, so it passes through.
            'body' => $message->is_deleted && $message->body === '' ? null : $message->body,
            'is_deleted' => $message->is_deleted,
            'created_at' => $message->created_at->toIso8601String(),
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'username' => $message->sender->username,
                'avatar_url' => $message->sender->avatar_url,
            ],
            'is_mine' => $message->user_id === $viewer->id,
            'can_delete' => ! $message->is_deleted && Gate::forUser($viewer)->allows('delete', $message),
            'can_report' => ! $message->is_deleted && $message->user_id !== $viewer->id,
        ];
    }
}
