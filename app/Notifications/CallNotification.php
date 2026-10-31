<?php

namespace App\Notifications;

use App\Enums\CallPushKind;
use App\Enums\NotificationCategory;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Messages\TimestampedWebPushMessage;
use App\Services\CallRings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * A push to the callee's devices (browsers and the Android app) about a
 * video call: the ring itself, then either "missed" in its place or a
 * quiet "over" that takes it down once the call was taken or refused
 * elsewhere. Only a push: the ring reaches open pages over the WebSocket,
 * and a page that opens from the notification asks the server for the
 * pending ring (CallRings).
 *
 * Queued, like every push; a ring that was settled in the second before
 * the worker gets to it is dropped (shouldSend).
 */
#[DeleteWhenMissingModels]
#[WithoutRelations]
class CallNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public CallPushKind $kind,
        public Conversation $conversation,
        public User $caller,
        public string $callId,
        public int $startedAt,
        public int $expiresAt,
    ) {}

    /**
     * @param  array{call_id: string, started_at: int, expires_at: int}  $ring
     */
    public static function about(CallPushKind $kind, Conversation $conversation, User $caller, array $ring): self
    {
        return new self($kind, $conversation, $caller, $ring['call_id'], $ring['started_at'], $ring['expires_at']);
    }

    public static function category(): NotificationCategory
    {
        return NotificationCategory::VideoCalls;
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if ($notifiable->wantsPush(self::category())) {
            return [WebPushChannel::class, FcmChannel::class];
        }

        return [];
    }

    /**
     * Asked by the queue worker right before the push goes out. A ring is
     * pushed only while it is still pending, to someone who has not muted
     * the conversation and is not looking at it (the page rings there);
     * "missed" and "over" go whenever.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        if (! $notifiable->wantsPush(self::category())) {
            return false;
        }

        if ($this->kind !== CallPushKind::Ring) {
            return true;
        }

        $participant = ConversationParticipant::query()
            ->where('conversation_id', $this->conversation->id)
            ->whereBelongsTo($notifiable)
            ->first();

        return $participant !== null
            && ! $participant->isMuted()
            && ! $this->conversation->isBeingViewedBy($notifiable)
            && (CallRings::pending($notifiable->id)['call_id'] ?? null) === $this->callId;
    }

    /**
     * The three kinds share a tag, so "missed" replaces the ring on the
     * device and "over" finds it to close it; and a topic, so a push the
     * push service still holds for an offline device is replaced too. A
     * ring is not worth delivering after it has run out (TTL).
     */
    public function toWebPush(User $notifiable, self $notification): TimestampedWebPushMessage
    {
        $name = $this->caller->name;
        $avatar = $this->caller->avatar_url !== null ? url($this->caller->avatar_url) : null;
        $kind = $this->kind->value;

        return (new TimestampedWebPushMessage)
            ->title(__("calls.push.{$kind}.title", ['name' => $name]))
            ->body(__("calls.push.{$kind}.body", ['name' => $name]))
            ->icon($avatar ?? '/icons/icon-192.png')
            ->badge('/icons/badge-96.png')
            ->tag("call-{$this->callId}")
            ->renotify()
            ->timestamp($this->startedAt)
            ->lang(app()->getLocale())
            ->data([
                'url' => route('messages.show', $this->conversation, false),
                'type' => $kind,
                'category' => self::category()->value,
                'conversation_id' => $this->conversation->id,
                'call_id' => $this->callId,
                'caller_name' => $name,
                'caller_avatar' => $avatar,
                'started_at' => $this->startedAt,
                'expires_at' => $this->expiresAt,
            ])
            ->options([
                'TTL' => match ($this->kind) {
                    CallPushKind::Ring => max(1, (int) ceil(($this->expiresAt - now()->getTimestampMs()) / 1000)),
                    CallPushKind::Over => 60,
                    CallPushKind::Missed => 86400,
                },
                'urgency' => $this->kind === CallPushKind::Missed ? 'normal' : 'high',
                'topic' => "call-{$this->conversation->id}",
            ]);
    }
}
