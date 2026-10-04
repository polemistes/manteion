<?php

namespace App\Listeners;

use App\Events\BadgesUpdated;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Sends the recipient's badge counts as soon as a notification has been
 * stored for them. Listening here rather than at the dispatch sites means
 * it runs wherever the notification is actually stored (the queue worker,
 * for queued ones), so the activity count it carries already includes it.
 */
class UpdateActivityBadge
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel === 'database' && $event->notifiable instanceof User) {
            BadgesUpdated::dispatch($event->notifiable->id);
        }
    }
}
