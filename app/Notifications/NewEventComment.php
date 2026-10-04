<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\User;

/**
 * Someone else commented on an event the recipient hosts. Withdrawn again
 * when the comment is deleted (see EventCommentController).
 */
class NewEventComment extends ActivityNotification
{
    public function __construct(
        public EventComment $comment,
    ) {}

    public static function category(): NotificationCategory
    {
        return NotificationCategory::EventComments;
    }

    public function actor(): User
    {
        return $this->comment->user;
    }

    public function subject(): Event
    {
        return $this->comment->event;
    }

    public function excerpt(): string
    {
        return self::snippet($this->comment->body);
    }

    /**
     * @return array<string, int>
     */
    protected function references(): array
    {
        return ['comment_id' => $this->comment->id];
    }

    protected function pushTitle(): string
    {
        return __('notifications.push.new_event_comment.title', ['event' => $this->comment->event->name]);
    }

    protected function pushBody(): string
    {
        return __('notifications.push.new_event_comment.body', [
            'name' => $this->comment->user->name,
            'excerpt' => $this->excerpt(),
        ]);
    }
}
