<?php

namespace App\Jobs;

use App\Enums\EventVisibility;
use App\Enums\FollowStatus;
use App\Models\ContactTagPreference;
use App\Models\Event;
use App\Models\User;
use App\Notifications\FollowedUserPublishedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Tells the owner's accepted followers about a newly published event,
 * honouring each follower's per-contact mutes. Runs off the request: with
 * many followers this is one query for their preferences plus the sends,
 * none of which the publisher should wait for.
 */
class NotifyFollowersOfEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public Event $event) {}

    public function handle(): void
    {
        $event = $this->event->fresh(['owner', 'organizer', 'tags']);

        if ($event === null || $event->followers_notified_at !== null) {
            return;
        }

        // Made private or deleted while this waited in the queue.
        if ($event->is_deleted || ! in_array($event->visibility, [EventVisibility::Public, EventVisibility::Searchable], true)) {
            return;
        }

        // An organizer's followers follow the organizer, not the person who
        // made the event; mutes are per person, so none apply.
        if ($event->organizer !== null) {
            $event->forceFill(['followers_notified_at' => now()])->save();

            $event->organizer->followers()->get()->each(fn (User $follower) => $follower->notify(
                (new FollowedUserPublishedEvent($event))->locale($follower->locale ?? 'en')
            ));

            return;
        }

        $followers = $event->owner->followers()
            ->wherePivot('status', FollowStatus::Accepted->value)
            ->get();

        $mutedTagIdsByFollower = ContactTagPreference::query()
            ->where('contact_user_id', $event->user_id)
            ->whereIn('user_id', $followers->modelKeys())
            ->get(['user_id', 'tag_id'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->pluck('tag_id'));

        $eventTagIds = $event->tags->pluck('id');

        $recipients = $followers->filter(function (User $follower) use ($mutedTagIdsByFollower, $eventTagIds) {
            $mutedTagIds = $mutedTagIdsByFollower->get($follower->id, collect());

            if ($mutedTagIds->contains(null)) {
                return false;
            }

            return $mutedTagIds->intersect($eventTagIds)->isEmpty();
        });

        $event->forceFill(['followers_notified_at' => now()])->save();

        // One send per follower, so each push is worded in their language.
        $recipients->each(fn (User $follower) => $follower->notify(
            (new FollowedUserPublishedEvent($event))->locale($follower->locale ?? 'en')
        ));
    }
}
