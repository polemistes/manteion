<?php

namespace App\Notifications;

use App\Enums\ConversationType;
use App\Enums\NotificationCategory;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Messages\TimestampedWebPushMessage;
use App\Services\BadgeCounts;
use App\Services\MessageSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\SerializesModels;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * A push to one recipient's devices (browsers and the Android app) about a
 * new message. Only a push:
 * messages have their own badge and list, so nothing is stored for the
 * Activity page, and open tabs hear about it over the WebSocket instead.
 *
 * Queued; a send whose message or recipient is gone by the time the queue
 * gets to it is dropped.
 */
#[DeleteWhenMissingModels]
class NewMessageReceived extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * How much of the message text a push shows.
     */
    public const PREVIEW_LENGTH = 140;

    public function __construct(
        public Message $message,
    ) {}

    public function category(): NotificationCategory
    {
        return $this->message->conversation->type === ConversationType::Group
            ? NotificationCategory::GroupMessages
            : NotificationCategory::DirectMessages;
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if ($notifiable->wantsPush($this->category())) {
            return [WebPushChannel::class, FcmChannel::class];
        }

        return [];
    }

    /**
     * Asked again by the queue worker right before the push goes out, as a
     * second is long enough for things to change: skip it if the message
     * was deleted, the recipient turned this kind of push off, left or
     * muted the conversation, or has the thread open in front of them.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        if ($this->message->is_deleted || ! $notifiable->wantsPush($this->category())) {
            return false;
        }

        $participant = ConversationParticipant::query()
            ->where('conversation_id', $this->message->conversation_id)
            ->whereBelongsTo($notifiable)
            ->first();

        return $participant !== null
            && ! $participant->isMuted()
            && ! $this->message->conversation->isBeingViewedBy($notifiable);
    }

    /**
     * Direct: the sender as title and the text as body. Group: the group as
     * title and "sender: text" as body. Without previews only that there is
     * a new message. Pushes about one conversation share a tag, so a phone
     * shows the latest instead of a pile.
     */
    public function toWebPush(User $notifiable, self $notification): TimestampedWebPushMessage
    {
        $conversation = $this->message->conversation;
        $sender = $this->message->sender;
        $isGroup = $conversation->type === ConversationType::Group;
        $text = MessageSummary::preview($this->message, self::PREVIEW_LENGTH) ?? '';

        $previews = $notifiable->showsMessagePreviews();

        $body = match (true) {
            ! $previews => __('chat.push.new_message'),
            $isGroup => __('chat.push.group_body', ['name' => $sender->name, 'text' => $text]),
            default => $text,
        };

        return (new TimestampedWebPushMessage)
            ->title($isGroup ? $conversation->titleFor($notifiable) : $sender->name)
            ->body($body)
            ->icon($sender->avatar_url !== null ? url($sender->avatar_url) : '/icons/icon-192.png')
            ->badge('/icons/badge-96.png')
            ->tag("conversation-{$conversation->id}")
            ->renotify()
            ->timestamp($this->message->created_at->getTimestampMs())
            ->lang(app()->getLocale())
            ->data([
                'url' => route('messages.show', $conversation, false),
                'type' => 'message',
                'category' => $this->category()->value,
                'conversation_id' => $conversation->id,
                'message_id' => $this->message->id,
                // The parts, for the Android app's conversation-style
                // notification; without previews no text.
                'conversation_type' => $conversation->type->value,
                'conversation_title' => $isGroup ? $conversation->titleFor($notifiable) : null,
                'sender_name' => $sender->name,
                'sender_avatar' => $sender->avatar_url !== null ? url($sender->avatar_url) : null,
                'text' => $previews ? $text : null,
                'badge_count' => array_sum(BadgeCounts::for($notifiable)),
            ])
            ->options([
                'TTL' => 86400,
                'urgency' => 'high',
                // A newer push about the same conversation replaces one the
                // push service still holds for an offline device.
                'topic' => "c{$conversation->id}",
            ]);
    }
}
