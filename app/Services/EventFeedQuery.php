<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EventVisibility;
use App\Enums\FollowStatus;
use App\Models\ContactTagPreference;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Organizer;
use App\Models\User;
use Carbon\CarbonImmutable;
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

        $organizerIds = $this->viewer->organizerIds()->all();

        return $this->base()
            ->where(function (Builder $query) use ($viewerId, $fullyMutedContactIds, $organizerIds) {
                $query->where('user_id', $viewerId)
                    ->orWhereHas('attendees', fn (Builder $attendees) => $attendees->where('user_id', $viewerId))
                    // A spokesperson sees everything of their organizers.
                    ->orWhereIn('organizer_id', $organizerIds)
                    // An organizer's public events reach whoever follows it.
                    ->orWhere(fn (Builder $query) => $query
                        ->whereIn('visibility', [EventVisibility::Public->value, EventVisibility::Searchable->value])
                        ->whereIn('organizer_id', DB::table('organizer_follows')->select('organizer_id')->where('user_id', $viewerId)))
                    // A person's public events reach the people following them.
                    ->orWhere(function (Builder $query) use ($viewerId, $fullyMutedContactIds) {
                        $query->whereIn('visibility', [EventVisibility::Public->value, EventVisibility::Searchable->value])
                            ->whereNull('organizer_id')
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
     * One person's events that the viewer may see, for their profile: the
     * ones the list would show, and their searchable ones.
     *
     * @return Builder<Event>
     */
    public function ofOwner(User $owner): Builder
    {
        return $this->base()
            ->where('user_id', $owner->id)
            ->whereNull('organizer_id')
            ->where(fn (Builder $query) => $query
                ->whereIn('events.id', $this->visible()->select('events.id'))
                ->orWhere('visibility', EventVisibility::Searchable));
    }

    /**
     * An organizer's events that the viewer may see, for its page.
     *
     * @return Builder<Event>
     */
    public function ofOrganizer(Organizer $organizer): Builder
    {
        return $this->base()
            ->where('organizer_id', $organizer->id)
            ->where(fn (Builder $query) => $query
                ->whereIn('events.id', $this->visible()->select('events.id'))
                ->orWhere('visibility', EventVisibility::Searchable));
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
     * Narrows to events happening on any day from $from to $until (YYYY-MM-DD
     * in the display time zone, either left open with null). An event counts
     * on every day it runs, so a weekend away that started on Friday still
     * shows on Saturday.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function happeningBetween(Builder $query, ?string $from, ?string $until): Builder
    {
        $timezone = (string) config('app.display_timezone');

        if ($from !== null) {
            $start = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();

            // One that ends at midnight is over before the day starts.
            $query->where(fn (Builder $query) => $query
                ->where('starts_at', '>=', $start)
                ->orWhere('ends_at', '>', $start));
        }

        if ($until !== null) {
            $query->where('starts_at', '<', CarbonImmutable::parse($until, $timezone)->addDay()->startOfDay()->utc());
        }

        return $query;
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
            ->with(['owner', 'organizer', 'tags', 'images', 'attendees' => fn ($attendees) => $attendees->where('user_id', $viewerId)])
            ->withCount([
                'attendees as attending_count' => fn ($attendees) => $attendees->where('status', AttendanceStatus::Attending),
                'attendees as interested_count' => fn ($attendees) => $attendees->where('status', AttendanceStatus::Interested),
            ]);
    }
}
