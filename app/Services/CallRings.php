<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Who is being rung right now. A ring is a broadcast, gone the moment it
 * is sent; this remembers it for the length of a ring, so a page that
 * opens late — from the push notification, after a reload, the app woken
 * by a tap — still finds it (GET calls.ringing). Settled when the call is
 * taken, refused or given up. One pending ring per person: a second caller
 * gets "busy" from the callee's browser anyway.
 *
 * @phpstan-type Ring array{conversation_id: int, call_id: string, from: array{id: int, name: string, avatar_url: string|null}, from_tab: string, started_at: int, expires_at: int}
 */
class CallRings
{
    /** How long a ring is kept: the browsers' 45 seconds and a little slack. */
    public const SECONDS = 50;

    /**
     * @return Ring
     */
    public static function ring(int $calleeId, Conversation $conversation, User $caller, string $callId, string $fromTab): array
    {
        $now = now();
        $ring = [
            'conversation_id' => $conversation->id,
            'call_id' => $callId,
            'from' => [
                'id' => $caller->id,
                'name' => $caller->name,
                'avatar_url' => $caller->avatar_url,
            ],
            'from_tab' => $fromTab,
            'started_at' => $now->getTimestampMs(),
            'expires_at' => $now->copy()->addSeconds(self::SECONDS)->getTimestampMs(),
        ];

        Cache::put(self::key($calleeId), $ring, $now->copy()->addSeconds(self::SECONDS));

        return $ring;
    }

    /**
     * The ring the person is being offered, if it has not run out.
     *
     * @return Ring|null
     */
    public static function pending(int $calleeId): ?array
    {
        $ring = Cache::get(self::key($calleeId));

        if (! is_array($ring) || ($ring['expires_at'] ?? 0) <= now()->getTimestampMs()) {
            return null;
        }

        /** @var Ring $ring */
        return $ring;
    }

    /**
     * Forgets the person's pending ring when it is this call's, and hands it
     * back; null when there was none (already settled, or another call's).
     *
     * @return Ring|null
     */
    public static function settle(int $calleeId, string $callId): ?array
    {
        $ring = self::pending($calleeId);

        if ($ring === null || $ring['call_id'] !== $callId) {
            return null;
        }

        Cache::forget(self::key($calleeId));

        return $ring;
    }

    private static function key(int $userId): string
    {
        return "calls.ringing.{$userId}";
    }
}
