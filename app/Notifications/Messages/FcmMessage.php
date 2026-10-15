<?php

namespace App\Notifications\Messages;

use NotificationChannels\WebPush\WebPushMessage;

/**
 * A push to the Android app, made from the notification's web push message
 * so there is one payload for both: a data-only FCM message whose `payload`
 * is the very JSON the service worker gets. The app's messaging service
 * builds the notification from it (sender's picture, conversation, reply),
 * which FCM's own notification messages can't do.
 */
class FcmMessage
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly array $payload,
        public readonly array $options = [],
    ) {}

    public static function fromWebPush(WebPushMessage $message): self
    {
        return new self($message->toArray(), $message->getOptions());
    }

    /**
     * FCM's `message` object, without the token.
     *
     * - data.payload: FCM data values must be strings, so the payload travels
     *   as one JSON string, the same shape as the web push body.
     * - priority high for every kind: a normal-priority data message waits
     *   for the phone to wake up, and the app shows each one it gets.
     * - collapse_key: the web push topic (one per conversation), so an
     *   offline phone gets the latest rather than a pile.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $android = ['priority' => 'high'];

        if (isset($this->options['TTL']) && is_numeric($this->options['TTL'])) {
            $android['ttl'] = ((int) $this->options['TTL']).'s';
        }

        if (isset($this->options['topic']) && is_string($this->options['topic'])) {
            $android['collapse_key'] = $this->options['topic'];
        }

        return [
            'data' => [
                'payload' => json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
            'android' => $android,
        ];
    }
}
