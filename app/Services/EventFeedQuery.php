<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EventVisibility;
use App\Enums\FollowStatus;
use App\Models\ContactTagPreference;
use App\Models\Event;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the event queries behind the list and search pages, so that
 * visibility, filters, distance ordering and pagination all happen in the
 * database rather than over every event loaded into memory.
 */
class EventFeedQuery
{
    public function __construct(private User $viewer) {}

    /**
     * Every event the viewer may see in their list: their own, those they
     * have an attendee row for (invited, or responded to), and public ones
     * from people they follow — minus followed contacts they muted fully,
     * and minus events tagged with something they muted for that contact.
     * An attendee row always wins over a mute: an explicit invitation is
     * never hidden.
     *
     * @return Builder<Event>
     */
    public function visible(): Builder
    {
        $viewerId = $this->viewer->id;

        $fullyMutedContactIds = ContactTagPreference::query()
            ->where('user_id', $viewerId)
            ->whereNull('tag_id')
            ->pluck('contact_user_id')
            ->all();

        return $this->base()
            ->where(function (Builder $query) use ($viewerId, $fullyMutedContactIds) {
                $query->where('user_id', $viewerId)
                    ->orWhereHas('attendees', fn (Builder $attendees) => $attendees->where('user_id', $viewerId))
                    ->orWhere(function (Builder $query) use ($viewerId, $fullyMutedContactIds) {
                        $query->whereIn('visibility', [EventVisibility::Public->value, EventVisibility::Searchable->value])
                            ->whereIn('user_id', Follow::query()
                                ->select('followed_id')
                                ->where('follower_id', $viewerId)
                                ->where('status', FollowStatus::Accepted))
                            ->whereNotIn('user_id', $fullyMutedContactIds)
                            ->whereNotExists(fn (QueryBuilder $muted) => $muted
                                ->select(DB::raw(1))
                                ->from('contact_tag_preferences')
                                ->join('event_tag', 'event_tag.tag_id', '=', 'contact_tag_preferences.tag_id')
                                ->whereColumn('event_tag.event_id', 'events.id')
                                ->whereColumn('contact_tag_preferences.contact_user_id', 'events.user_id')
                                ->where('contact_tag_preferences.user_id', $viewerId));
                    });
            });
    }

    /**
     * Events anyone may find: those on the searchable tier.
     *
     * @return Builder<Event>
     */
    public function searchable(): Builder
    {
        return $this->base()->where('visibility', EventVisibility::Searchable);
    }

    /**
     * @param  Builder<Event>  $query
     * @param  array<int, string>  $slugs
     * @return Builder<Event>
     */
    public function withAnyTag(Builder $query, array $slugs): Builder
    {
        return $query->when($slugs !== [], fn (Builder $query) => $query
            ->whereHas('tags', fn (Builder $tags) => $tags->whereIn('slug', $slugs)));
    }

    /**
     * Orders nearest-first, events without coordinates last. The ordering
     * uses an equirectangular approximation (plain arithmetic, so it runs
     * on SQLite and MySQL alike); the exact distance shown to the user is
     * computed afterwards for the rows on the page.
     *
     * @param  Builder<Event>  $query
     * @param  array{0: float, 1: float}|null  $nearPoint
     * @return Builder<Event>
     */
    public function ordered(Builder $query, ?array $nearPoint): Builder
    {
        if ($nearPoint === null) {
            return $query->orderBy('starts_at')->orderBy('id');
        }

        [$lat, $lng] = $nearPoint;
        $lngScale = cos(deg2rad($lat));

        return $query
            ->orderByRaw('CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw(
                '((latitude - ?) * (latitude - ?)) + ((longitude - ?) * ? * (longitude - ?) * ?)',
                [$lat, $lat, $lng, $lngScale, $lng, $lngScale]
            )
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /**
     * @return Builder<Event>
     */
    private function base(): Builder
    {
        $viewerId = $this->viewer->id;

        return Event::query()
            ->where('is_deleted', false)
            ->with(['owner', 'tags', 'images', 'attendees' => fn ($attendees) => $attendees->where('user_id', $viewerId)])
            ->withCount([
                'attendees as attending_count' => fn ($attendees) => $attendees->where('status', AttendanceStatus::Attending),
                'attendees as interested_count' => fn ($attendees) => $attendees->where('status', AttendanceStatus::Interested),
            ]);
    }
}
