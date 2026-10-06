<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Event;
use App\Models\Leaflet;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ActivityFeed;
use App\Services\BadgeCounts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Something someone did that concerns the recipient: an invitation, a
 * comment on their event, a friend or follow request and so on. Stored for
 * the Activity page, broadcast so open tabs can show it straight away, and
 * pushed to the recipient's devices when they turned its category on.
 *
 * Every kind stores the same data, about one subject (an event, a leaflet
 * or a user), so the Activity page lists them alike and opening the subject
 * marks them read (see ActivityFeed). A send whose models are gone by the
 * time the queue gets to it (a cancelled request, a deleted comment) is
 * dropped. Models are queued without whatever relations the request had
 * loaded (an event's whole guest list, say); each send loads what it uses.
 */
#[DeleteWhenMissingModels]
#[WithoutRelations]
abstract class ActivityNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * How much of a comment or reply the notification quotes.
     */
    public const EXCERPT_LENGTH = 140;

    /**
     * How much of a leaflet stands in for its title.
     */
    public const LEAFLET_TITLE_LENGTH = 80;

    abstract public static function category(): NotificationCategory;

    /**
     * The person whose action the notification is about.
     */
    abstract public function actor(): User;

    /**
     * The event, leaflet, user or organizer the notification is about.
     */
    abstract public function subject(): Event|Leaflet|User|Organizer;

    abstract protected function pushTitle(): string;

    abstract protected function pushBody(): string;

    /**
     * What was written, for comments and replies.
     */
    public function excerpt(): ?string
    {
        return null;
    }

    /**
     * Whether a request still awaits the recipient's answer, for kinds that
     * can be one.
     */
    public function pending(): ?bool
    {
        return null;
    }

    /**
     * Ids stored beside the uniform data so the notification can be found
     * again, e.g. to withdraw it when the comment it quotes is deleted.
     *
     * @return array<string, int>
     */
    protected function references(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($notifiable->wantsPush(static::category())) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * The stored data, the same keys for every kind.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $subject = $this->describeSubject();

        return [
            'actor_id' => $this->actor()->id,
            'subject_type' => $subject['type'],
            'subject_id' => $subject['id'],
            'subject_title' => $subject['title'],
            'url' => $subject['url'],
            'excerpt' => $this->excerpt(),
            'pending' => $this->pending(),
            ...$this->references(),
        ];
    }

    /**
     * The item as the Activity page lists it, so open tabs can add it
     * without a reload. Sent as soon as the queue gets to it rather than
     * queued a second time.
     */
    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage(ActivityFeed::item(
            id: (string) $this->id,
            type: static::class,
            data: $this->toArray($notifiable),
            actor: $this->actor(),
            unread: true,
            createdAt: now(),
        )))->onConnection('sync');
    }

    /**
     * The `type` of the broadcast: the class basename, as the Activity page
     * reports it.
     */
    public function broadcastType(): string
    {
        return class_basename(static::class);
    }

    public function toWebPush(User $notifiable, self $notification): WebPushMessage
    {
        $subject = $this->describeSubject();
        $type = class_basename(static::class);

        return (new WebPushMessage)
            ->title($this->pushTitle())
            ->body($this->pushBody())
            ->icon($this->actor()->avatar_url ?? '/icons/icon-192.png')
            ->badge('/icons/badge-96.png')
            ->tag("{$type}-{$subject['id']}")
            ->renotify()
            ->lang(app()->getLocale())
            ->data([
                'url' => $subject['url'],
                'type' => $type,
                'badge_count' => array_sum(BadgeCounts::for($notifiable)),
            ])
            ->options(['TTL' => 86400, 'urgency' => 'normal']);
    }

    /**
     * A single-line copy of what someone wrote, shortened to whole words.
     * (Not Str::limit()'s word mode: it strips anything tag-like, and with
     * it the rest of a text like "I <3 this".)
     */
    protected static function snippet(string $text, int $length = self::EXCERPT_LENGTH): string
    {
        $text = Str::squish($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');

        // End on a whole word, unless the text is one long word.
        if (mb_substr($text, $length, 1) !== ' ' && $lastSpace !== false) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut).'…';
    }

    /**
     * @return array{type: string, id: int, title: string, url: string}
     */
    private function describeSubject(): array
    {
        $subject = $this->subject();

        return match (true) {
            $subject instanceof Event => [
                'type' => 'event',
                'id' => $subject->id,
                'title' => $subject->name,
                'url' => route('events.show', $subject, false),
            ],
            // Leaflets have no title; the start of the text stands in.
            $subject instanceof Leaflet => [
                'type' => 'leaflet',
                'id' => $subject->id,
                'title' => self::snippet($subject->body, self::LEAFLET_TITLE_LENGTH),
                'url' => route('leaflets.show', $subject, false),
            ],
            $subject instanceof User => [
                'type' => 'user',
                'id' => $subject->id,
                'title' => $subject->name,
                'url' => route('users.show', $subject, false),
            ],
            $subject instanceof Organizer => [
                'type' => 'organizer',
                'id' => $subject->id,
                'title' => $subject->name,
                'url' => route('organizers.show', $subject, false),
            ],
        };
    }
}
