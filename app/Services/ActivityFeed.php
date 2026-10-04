<?php

namespace App\Services;

use App\Events\BadgesUpdated;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Support\Header;

/**
 * The user's activity (their database notifications) as the Activity page
 * and the live broadcasts show it, and its read state: everything is read
 * once the Activity page is opened, and whatever concerns one event,
 * leaflet or user once its page is.
 */
class ActivityFeed
{
    public const LIMIT = 100;

    /**
     * The newest notifications, with every actor loaded in one query.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for(User $user, int $limit = self::LIMIT): array
    {
        $notifications = $user->notifications()->limit($limit)->get();

        $actors = User::query()
            ->whereKey($notifications->pluck('data.actor_id')->filter()->unique()->values())
            ->get(['id', 'name', 'username', 'avatar_path'])
            ->keyBy('id');

        return $notifications
            ->map(fn (DatabaseNotification $notification) => self::item(
                id: $notification->id,
                type: $notification->type,
                data: $notification->data,
                actor: $actors->get($notification->data['actor_id'] ?? null),
                unread: $notification->read_at === null,
                createdAt: $notification->created_at,
            ))
            ->values()
            ->all();
    }

    /**
     * One activity item, from a stored notification or one being sent.
     *
     * @param  array<string, mixed>  $data
     * @return array{id: string, type: string, category: string|null, actor: array{id: int, name: string, username: string, avatar_url: string|null}|null, subject_type: string|null, subject_id: int|null, subject_title: string|null, excerpt: string|null, pending: bool|null, url: string|null, unread: bool, created_at: string}
     */
    public static function item(string $id, string $type, array $data, ?User $actor, bool $unread, CarbonInterface $createdAt): array
    {
        return [
            'id' => $id,
            'type' => class_basename($type),
            'category' => is_a($type, ActivityNotification::class, true) ? $type::category()->value : null,
            'actor' => $actor === null ? null : [
                'id' => $actor->id,
                'name' => $actor->name,
                'username' => $actor->username,
                'avatar_url' => $actor->avatar_url,
            ],
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'subject_title' => $data['subject_title'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'pending' => $data['pending'] ?? null,
            'url' => $data['url'] ?? null,
            'unread' => $unread,
            'created_at' => $createdAt->toIso8601String(),
        ];
    }

    /**
     * Whether the request is the user opening a page (a full load or an
     * Inertia visit), so its activity has been seen. Not a partial reload,
     * which a live update or a background tab makes, not a hover prefetch,
     * and not an administrator browsing as them.
     */
    public static function seenByUser(Request $request): bool
    {
        return ! $request->hasHeader(Header::PARTIAL_COMPONENT)
            && ! $request->prefetch()
            && ! ($request->hasSession() && $request->session()->has('impersonator_id'));
    }

    /**
     * Marks the user's unread notifications read: all of them, or those
     * about one subject (`event`, `leaflet` or `user`). Their devices hear
     * about it when that changed the count.
     */
    public static function markRead(User $user, ?string $subjectType = null, ?int $subjectId = null): int
    {
        $marked = $user->unreadNotifications()
            ->when($subjectType !== null, fn ($query) => $query
                ->where('data->subject_type', $subjectType)
                ->where('data->subject_id', $subjectId))
            ->update(['read_at' => now()]);

        if ($marked > 0) {
            BadgesUpdated::dispatch($user->id);
        }

        return $marked;
    }

    /**
     * Deletes the recipient's notifications of one kind that point at
     * something deleted (found by its reference key, e.g. `comment_id`), so
     * its text does not live on in their activity. Their devices hear about
     * it when one of them was still unread.
     *
     * @param  class-string<ActivityNotification>  $type
     */
    public static function withdraw(User $recipient, string $type, string $reference, int $id): void
    {
        $notifications = fn () => $recipient->notifications()
            ->where('type', $type)
            ->where("data->{$reference}", $id);

        $wasUnread = $notifications()->whereNull('read_at')->exists();

        $notifications()->delete();

        if ($wasUnread) {
            BadgesUpdated::dispatch($recipient->id);
        }
    }
}
