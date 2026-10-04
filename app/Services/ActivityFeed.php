<?php

namespace App\Services;

use App\Events\BadgesUpdated;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Notifications\FriendRequestReceived;
use App\Notifications\NewFollower;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
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
     * The newest notifications, each with what the Activity page needs to
     * answer it in place (see context()). Actors, events and requests load
     * in one query each.
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

        $context = self::context($user, $notifications);

        return $notifications
            ->map(fn (DatabaseNotification $notification) => [
                ...self::item(
                    id: $notification->id,
                    type: $notification->type,
                    data: $notification->data,
                    actor: $actors->get($notification->data['actor_id'] ?? null),
                    unread: $notification->read_at === null,
                    createdAt: $notification->created_at,
                ),
                'context' => $context($notification),
            ])
            ->values()
            ->all();
    }

    /**
     * For each notification: the event it is about as the user may still
     * see it (date, cover, their answer; null once deleted or out of
     * sight), the friend or follow request it brought and whether that is
     * still open (null once withdrawn), and, for a new follower, whether
     * the user follows them back.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return Closure(DatabaseNotification): array{event: array<string, mixed>|null, request: array{id: int, status: string}|null, following_actor: string|null}
     */
    private static function context(User $user, Collection $notifications): Closure
    {
        $about = fn (string $subjectType) => $notifications
            ->filter(fn (DatabaseNotification $notification) => ($notification->data['subject_type'] ?? null) === $subjectType)
            ->pluck('data.subject_id')
            ->filter()
            ->unique()
            ->values();

        $events = (new EventFeedQuery($user))->visible()
            ->whereKey($about('event'))
            ->get()
            ->keyBy('id');

        $friendships = Friendship::query()
            ->whereKey($notifications->pluck('data.friendship_id')->filter()->unique()->values())
            ->get(['id', 'status'])
            ->keyBy('id');

        $follows = Follow::query()
            ->whereKey($notifications->pluck('data.follow_id')->filter()->unique()->values())
            ->get(['id', 'status'])
            ->keyBy('id');

        $followingActors = Follow::query()
            ->where('follower_id', $user->id)
            ->whereIn('followed_id', $notifications->pluck('data.actor_id')->filter()->unique()->values())
            ->get(['followed_id', 'status'])
            ->mapWithKeys(fn (Follow $follow) => [$follow->followed_id => $follow->status->value]);

        return function (DatabaseNotification $notification) use ($user, $events, $friendships, $follows, $followingActors): array {
            $data = $notification->data;
            $event = ($data['subject_type'] ?? null) === 'event' ? $events->get($data['subject_id'] ?? null) : null;
            $attendee = $event?->attendees->first();

            $request = match ($notification->type) {
                FriendRequestReceived::class => $friendships->get($data['friendship_id'] ?? null),
                NewFollower::class => $follows->get($data['follow_id'] ?? null),
                default => null,
            };

            $following = $notification->type === NewFollower::class
                ? $followingActors->get($data['actor_id'] ?? null)
                : null;

            return [
                'event' => $event === null ? null : [
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'ends_at' => $event->ends_at?->toIso8601String(),
                    'cover' => $event->images->first()?->presentation(),
                    'host_name' => $event->owner->name,
                    'is_host' => $event->user_id === $user->id,
                    'is_over' => ($event->ends_at ?? $event->starts_at)->isPast(),
                    'my_status' => $attendee?->status->value,
                    'my_attendance_visibility' => $attendee?->attendance_visibility->value,
                ],
                'request' => $request === null ? null : ['id' => $request->id, 'status' => $request->status->value],
                'following_actor' => $following,
            ];
        };
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
     * Deletes every notification about an event or leaflet that is gone,
     * whoever got them, so no one's Activity keeps pointing at it. Those for
     * whom one was still unread hear about their new count.
     */
    public static function withdrawAbout(string $subjectType, int $subjectId): void
    {
        $notifications = fn () => DatabaseNotification::query()
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('data->subject_type', $subjectType)
            ->where('data->subject_id', $subjectId);

        $unreadFor = $notifications()->whereNull('read_at')->distinct()->pluck('notifiable_id');

        $notifications()->delete();

        $unreadFor->each(fn (mixed $userId) => BadgesUpdated::dispatch((int) $userId));
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
