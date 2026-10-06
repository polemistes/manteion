<?php

namespace App\Http\Controllers;

use App\Enums\AdminAlert;
use App\Enums\AttendanceStatus;
use App\Enums\AttendeeSource;
use App\Enums\ContactGroupType;
use App\Enums\EventVisibility;
use App\Enums\FollowStatus;
use App\Enums\ReportStatus;
use App\Events\EventActivity;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Jobs\NotifyFollowersOfEvent;
use App\Models\ContactGroup;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\EventImage;
use App\Models\Organizer;
use App\Models\Reaction;
use App\Models\Tag;
use App\Models\User;
use App\Services\ActivityFeed;
use App\Services\AdminAlerts;
use App\Services\EventFeedQuery;
use App\Services\ImageProcessor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private ImageProcessor $imageProcessor) {}

    public const PER_PAGE = 30;

    /** @var list<string> */
    public const CALENDAR_VIEWS = ['month', 'week', 'day'];

    public const CALENDAR_MAX_EVENTS = 500;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $feed = new EventFeedQuery($user);
        $nearPoint = $this->nearPointFrom($request);
        $followedIds = $this->followedIds($user);

        $query = $this->applyContactFilters($feed->withAnyTag($feed->visible(), $request->array('tags')), $request, $user, $followedIds);

        $term = trim((string) $request->string('q'));
        if ($term !== '') {
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('place', 'like', "%{$term}%"));
        }

        $startDateFilter = $request->has('start_date') ? $this->dateFrom($request, 'start_date') : $this->today();
        $endDateFilter = $this->dateFrom($request, 'end_date');
        $feed->happeningBetween($query, $startDateFilter ?: null, $endDateFilter ?: null);

        $events = $feed->ordered($query, $nearPoint)->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('events/Index', [
            'events' => $this->summarizeEvents($events->getCollection(), $user, $nearPoint),
            'pagination' => $this->summarizePagination($events),
            'invitations' => fn () => $this->summarizeEvents($this->pendingInvitationsFor($user, $feed), $user),
            'comingUp' => fn () => $this->summarizeEvents($this->comingUpFor($user, $feed), $user),
            'calendarDays' => fn () => $this->eventDaysThisMonth($feed),
            ...$this->filterPanelProps($user, $followedIds),
            'filters' => [
                ...$request->only(['tags', 'owner_filter', 'group_id', 'near_lat', 'near_lng', 'near_label', 'mine', 'q']),
                'start_date' => $startDateFilter,
                'end_date' => $endDateFilter,
            ],
        ]);
    }

    /**
     * The same events as the list, laid out by date: every event running on
     * a day of the month grid (whole weeks), the week or the day, with days
     * in the display time zone.
     */
    public function calendar(Request $request): Response
    {
        $user = $request->user();
        $feed = new EventFeedQuery($user);
        $followedIds = $this->followedIds($user);

        $view = in_array($request->string('view')->toString(), self::CALENDAR_VIEWS, true)
            ? $request->string('view')->toString()
            : 'month';
        $today = $this->today();
        $anchor = CarbonImmutable::parse($this->dateFrom($request, 'date') ?: $today);

        [$firstDay, $lastDay] = match ($view) {
            'month' => [
                $anchor->startOfMonth()->startOfWeek(CarbonInterface::MONDAY),
                $anchor->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY),
            ],
            'week' => [$anchor->startOfWeek(CarbonInterface::MONDAY), $anchor->endOfWeek(CarbonInterface::SUNDAY)],
            default => [$anchor, $anchor],
        };

        $query = $this->applyContactFilters($feed->withAnyTag($feed->visible(), $request->array('tags')), $request, $user, $followedIds);

        $events = $feed->happeningBetween($query, $firstDay->toDateString(), $lastDay->toDateString())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(self::CALENDAR_MAX_EVENTS)
            ->get();

        return Inertia::render('events/Calendar', [
            'events' => $this->summarizeEvents($events, $user),
            'calendar' => [
                'view' => $view,
                'date' => $anchor->toDateString(),
                'today' => $today,
            ],
            ...$this->filterPanelProps($user, $followedIds),
            'filters' => $request->only(['tags', 'owner_filter', 'group_id', 'mine']),
        ]);
    }

    public function search(Request $request): Response
    {
        $term = trim((string) $request->string('q'));
        $viewer = $request->user();
        $feed = new EventFeedQuery($viewer);
        $nearPoint = $this->nearPointFrom($request);

        $query = $feed->withAnyTag($feed->searchable(), $request->array('tags'))
            ->when($term !== '', fn ($query) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('place', 'like', "%{$term}%")
            ));
        $feed->happeningBetween($query, $this->today(), null);

        $events = $feed->ordered($query, $nearPoint)->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('events/Search', [
            'q' => $term,
            'categories' => Tag::categoriesTree(),
            'events' => $this->summarizeEvents($events->getCollection(), $viewer, $nearPoint),
            'pagination' => $this->summarizePagination($events),
            'filters' => $request->only(['tags', 'near_lat', 'near_lng']),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('events/Create', [
            'categories' => Tag::categoriesTree(),
            // The organizers the user may host it as.
            'organizers' => $request->user()->organizers()->get()->map(fn (Organizer $organizer) => $organizer->summary())->values()->all(),
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        // Hosted by one of the user's organizers, or by themself.
        $organizerId = $request->validated('organizer_id');
        abort_if($organizerId !== null && ! $request->user()->organizerIds()->contains((int) $organizerId), 403);

        $event = $request->user()->events()->create([
            ...$request->safe()->only([
                'name', 'place', 'latitude', 'longitude', 'city', 'country', 'country_code', 'description', 'starts_at', 'ends_at', 'visibility', 'allow_invitee_invites', 'allow_invitee_comments',
            ]),
            'organizer_id' => $organizerId === null ? null : (int) $organizerId,
        ]);

        $this->syncTags($event, $request->input('tag_ids', []));
        $this->storeImage($event, $request->file('image'), $request->input('image_fit'));

        if ($event->visibility !== EventVisibility::Private) {
            NotifyFollowersOfEvent::dispatch($event);
        }

        AdminAlerts::send(AdminAlert::EventCreated, $request->user(), [
            'host' => $event->hostName(),
            'event' => $event->name,
            'visibility' => $event->visibility->value,
            'starts' => $event->starts_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i'),
        ], route('events.show', $event));

        return redirect()->route('events.show', $event);
    }

    public function show(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);

        $viewer = $request->user();
        $event->load(['owner', 'organizer', 'tags', 'images', 'comments.user', 'comments.reactions', 'attendees.user', 'attendees.invitedBy']);

        // Opening the event means its invitation, comments and so on have
        // been seen.
        if (ActivityFeed::seenByUser($request)) {
            ActivityFeed::markRead($viewer, 'event', $event->id);
        }

        $isOwner = $event->isHostedBy($viewer);
        $attendee = $event->attendeeFor($viewer);
        $viewerFriendIds = $viewer->friendIds();
        $canInvite = Gate::allows('invite', $event);

        // An attendee who limited their visibility is left out entirely for
        // viewers outside it, not merely shown without a status: being
        // listed at all reveals that they were invited.
        $visibleAttendees = $event->attendees
            ->filter(fn (EventAttendee $attendee) => $isOwner || $attendee->user_id === $viewer->id || $attendee->isVisibleGiven($viewerFriendIds))
            ->values();

        // The comment policy reads the comment's event; hand it this loaded
        // instance so no comment re-fetches the event and its attendees.
        $event->comments->each(fn (EventComment $comment) => $comment->setRelation('event', $event));

        return Inertia::render('events/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'place' => $event->place,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'city' => $event->city,
                'country' => $event->country,
                'country_code' => $event->country_code === null ? null : strtoupper($event->country_code),
                'description' => $event->description,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'visibility' => $event->visibility->value,
                'allow_invitee_invites' => $event->allow_invitee_invites,
                'allow_invitee_comments' => $event->allow_invitee_comments,
                'is_searchable_paid' => $event->searchable_paid_at !== null,
                'owner' => ['id' => $event->owner->id, 'name' => $event->owner->name, 'username' => $event->owner->username, 'avatar_url' => $event->owner->avatar_url],
                'organizer' => $event->organizer?->summary(),
                'tags' => $event->tags->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])->values(),
                'images' => $event->images->map(fn (EventImage $image) => $image->presentation())->values(),
                'is_owner' => $isOwner,
                'is_deleted' => $event->is_deleted,
                'can_invite' => $canInvite,
                'can_comment' => Gate::allows('comment', $event),
                'can_delete' => Gate::allows('delete', $event),
                'can_report' => ! $event->is_deleted && Gate::allows('view', $event) && ! $isOwner,
            ],
            'myAttendance' => $attendee === null ? null : [
                'status' => $attendee->status->value,
                'attendance_visibility' => $attendee->attendance_visibility->value,
            ],
            'invitation' => $this->pendingInvitation($attendee),
            'attendees' => $visibleAttendees
                ->map(fn (EventAttendee $attendee) => [
                    'id' => $attendee->id,
                    'user' => ['id' => $attendee->user->id, 'name' => $attendee->user->name, 'username' => $attendee->user->username, 'avatar_url' => $attendee->user->avatar_url],
                    'status' => $attendee->status->value,
                ])->values(),
            'comments' => $event->comments->map(fn (EventComment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'is_deleted' => $comment->is_deleted,
                'created_at' => $comment->created_at->toIso8601String(),
                'user' => ['id' => $comment->user->id, 'name' => $comment->user->name, 'username' => $comment->user->username, 'avatar_url' => $comment->user->avatar_url],
                'can_delete' => $viewer->can('delete', $comment),
                // The viewer has already been authorised to view the event,
                // and with it every comment on it.
                'can_report' => ! $comment->is_deleted && $comment->user_id !== $viewer->id,
                'reactions' => Reaction::summarize($comment->reactions, $viewer),
            ])->values(),
            // Only for someone who may invite, and without only the guests
            // they can see: leaving out a hidden one would give it away.
            // Inviting someone already invited changes nothing.
            'inviteCandidates' => ! $canInvite ? [] : $viewer->friends()
                ->reject(fn (User $friend) => $visibleAttendees->contains('user_id', $friend->id))
                ->map(fn (User $friend) => ['id' => $friend->id, 'name' => $friend->name, 'username' => $friend->username, 'avatar_url' => $friend->avatar_url])
                ->values(),
        ]);
    }

    public function edit(Event $event): Response
    {
        Gate::authorize('update', $event);

        $event->load(['tags', 'images']);

        return Inertia::render('events/Edit', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'place' => $event->place,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'city' => $event->city,
                'country' => $event->country,
                'country_code' => $event->country_code === null ? null : strtoupper($event->country_code),
                'description' => $event->description,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'visibility' => $event->visibility->value,
                'is_searchable' => $event->visibility === EventVisibility::Searchable,
                'allow_invitee_invites' => $event->allow_invitee_invites,
                'allow_invitee_comments' => $event->allow_invitee_comments,
                'tag_ids' => $event->tags->pluck('id')->values(),
                'image' => $event->images->first()?->presentation(),
            ],
            'categories' => Tag::categoriesTree(),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $visibility = EventVisibility::from($request->string('visibility')->value());

        // The searchable tier, once bought, survives visibility edits: making
        // the event private hides it, and making it public again restores it.
        if ($event->searchable_paid_at !== null && $visibility === EventVisibility::Public) {
            $visibility = EventVisibility::Searchable;
        }

        $wasPrivate = $event->visibility === EventVisibility::Private;

        $event->update([
            ...$request->safe()->only(['name', 'place', 'latitude', 'longitude', 'city', 'country', 'country_code', 'description', 'starts_at', 'ends_at', 'allow_invitee_invites', 'allow_invitee_comments']),
            'visibility' => $visibility,
        ]);

        $this->syncTags($event, $request->input('tag_ids', []));
        $this->storeImage($event, $request->file('image'), $request->input('image_fit'), $request->exists('image_fit'));

        if ($wasPrivate && $visibility !== EventVisibility::Private && $event->followers_notified_at === null) {
            NotifyFollowersOfEvent::dispatch($event);
        }

        broadcast(new EventActivity($event->id, 'updated'))->toOthers();

        return redirect()->route('events.show', $event);
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        if ($request->user()->isStaff() && $request->boolean('leave_placeholder')) {
            $event->update(['description' => __('messages.moderation.deleted_placeholder'), 'is_deleted' => true]);

            $event->reports()->where('status', ReportStatus::Pending)->update([
                'status' => ReportStatus::Resolved,
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            ActivityFeed::withdrawAbout('event', $event->id);
            broadcast(new EventActivity($event->id, 'deleted'))->toOthers();

            return redirect()->route('events.index');
        }

        foreach ($event->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $event->reports()->where('status', ReportStatus::Pending)->update([
            'status' => ReportStatus::Resolved,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $event->delete();

        ActivityFeed::withdrawAbout('event', $event->id);
        broadcast(new EventActivity($event->id, 'deleted'))->toOthers();

        return redirect()->route('events.index');
    }

    /**
     * The card shape of each event, from the viewer's side: their answer and
     * invitation, the cover's size and shape, and up to four faces of people
     * going (friends first, only those whose answer the viewer may see).
     *
     * @param  Collection<int, Event>  $events
     * @param  array{0: float, 1: float}|null  $nearPoint
     * @return array<int, array<string, mixed>>
     */
    private function summarizeEvents(Collection $events, User $viewer, ?array $nearPoint = null): array
    {
        $faces = $this->goingFaces($events, $viewer);

        return $events->map(function (Event $event) use ($viewer, $nearPoint, $faces) {
            $attendee = $event->attendeeFor($viewer);

            return [
                'id' => $event->id,
                'name' => $event->name,
                'description' => Str::limit(preg_replace('/\s+/', ' ', $event->description) ?? '', 220),
                'place' => $event->place,
                'city' => $event->city,
                'country' => $event->country,
                'country_code' => $event->country_code === null ? null : strtoupper($event->country_code),
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'visibility' => $event->visibility->value,
                'owner' => ['id' => $event->owner->id, 'name' => $event->owner->name, 'username' => $event->owner->username, 'avatar_url' => $event->owner->avatar_url],
                'organizer' => $event->organizer?->summary(),
                'is_host' => $event->isHostedBy($viewer),
                'tags' => $event->tags->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])->values()->all(),
                'my_status' => $attendee?->status->value,
                'my_attendance_visibility' => $attendee?->attendance_visibility->value,
                'invitation' => $this->pendingInvitation($attendee),
                'cover' => $event->images->first()?->presentation(),
                'attending_count' => $event->attending_count,
                'interested_count' => $event->interested_count,
                'going' => $faces[$event->id] ?? [],
                'distance_km' => $nearPoint !== null && $event->latitude !== null && $event->longitude !== null
                    ? round($this->haversineKm($nearPoint[0], $nearPoint[1], $event->latitude, $event->longitude), 1)
                    : null,
            ];
        })->values()->all();
    }

    /**
     * Who invited the viewer, while the invitation is still unanswered.
     *
     * @return array{by: array{id: int, name: string, avatar_url: string|null}|null, at: string}|null
     */
    private function pendingInvitation(?EventAttendee $attendee): ?array
    {
        if ($attendee === null || $attendee->source !== AttendeeSource::Invited || $attendee->status !== AttendanceStatus::NoResponse) {
            return null;
        }

        $by = $attendee->invitedBy;

        return [
            'by' => $by === null ? null : ['id' => $by->id, 'name' => $by->name, 'avatar_url' => $by->avatar_url],
            'at' => ($attendee->created_at ?? now())->toIso8601String(),
        ];
    }

    /**
     * Up to four people going to each event that the viewer may see as
     * going, friends first. One query for the whole page.
     *
     * @param  Collection<int, Event>  $events
     * @return array<int, list<array{id: int, name: string, avatar_url: string|null}>>
     */
    private function goingFaces(Collection $events, User $viewer): array
    {
        if ($events->isEmpty()) {
            return [];
        }

        $friendIds = $viewer->friendIds();
        $hostedIds = $events->filter(fn (Event $event) => $event->isHostedBy($viewer))->pluck('id')->flip();

        return EventAttendee::query()
            ->whereIn('event_id', $events->pluck('id')->all())
            ->where('status', AttendanceStatus::Attending)
            ->with('user:id,name,avatar_path')
            ->orderBy('id')
            ->get()
            ->groupBy('event_id')
            ->map(fn (Collection $attendees, int $eventId) => array_values($attendees
                ->filter(fn (EventAttendee $attendee) => $hostedIds->has($eventId)
                    || $attendee->user_id === $viewer->id
                    || $attendee->isVisibleGiven($friendIds))
                ->sortBy(fn (EventAttendee $attendee) => $friendIds->contains($attendee->user_id) ? 0 : 1)
                ->take(4)
                ->map(fn (EventAttendee $attendee) => [
                    'id' => $attendee->user->id,
                    'name' => $attendee->user->name,
                    'avatar_url' => $attendee->user->avatar_url,
                ])
                ->all()))
            ->all();
    }

    /**
     * Upcoming events the user was invited to and hasn't answered, soonest
     * first: the feed's "Waiting for your answer".
     *
     * @return Collection<int, Event>
     */
    private function pendingInvitationsFor(User $user, EventFeedQuery $feed): Collection
    {
        return $feed->visible()
            ->whereHas('attendees', fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->where('source', AttendeeSource::Invited)
                ->where('status', AttendanceStatus::NoResponse))
            ->where('starts_at', '>=', CarbonImmutable::parse($this->today(), (string) config('app.display_timezone'))->utc())
            ->orderBy('starts_at')
            ->limit(10)
            ->get();
    }

    /**
     * The next three events the user is going to, may go to, or hosts.
     *
     * @return Collection<int, Event>
     */
    private function comingUpFor(User $user, EventFeedQuery $feed): Collection
    {
        return $feed->visible()
            ->where(fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->orWhereIn('organizer_id', $user->organizerIds()->all())
                ->orWhereHas('attendees', fn (Builder $attendees) => $attendees
                    ->where('user_id', $user->id)
                    ->whereIn('status', [AttendanceStatus::Attending, AttendanceStatus::Interested])))
            // Not over yet: still to start, still running, or without an
            // end and started today (see Event::isOver).
            ->where(fn (Builder $query) => $query
                ->where('starts_at', '>=', now())
                ->orWhere('ends_at', '>=', now())
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('ends_at')
                    ->where('starts_at', '>=', CarbonImmutable::parse($this->today(), (string) config('app.display_timezone'))->utc())))
            ->orderBy('starts_at')
            ->limit(3)
            ->get();
    }

    /**
     * The days of this month (in the display time zone) with an event the
     * user can see, for the month in the feed's aside. An event marks every
     * day it runs.
     *
     * @return array{month: string, days: list<string>}
     */
    private function eventDaysThisMonth(EventFeedQuery $feed): array
    {
        $timezone = (string) config('app.display_timezone');
        $first = CarbonImmutable::now($timezone)->startOfMonth();
        $last = $first->endOfMonth()->startOfDay();
        $days = [];

        $feed->happeningBetween($feed->visible(), $first->toDateString(), $last->toDateString())
            ->toBase()
            ->select(['events.starts_at', 'events.ends_at'])
            ->get()
            ->each(function (object $row) use (&$days, $timezone, $first, $last): void {
                $localDay = fn (string $value) => CarbonImmutable::parse($value, 'UTC')->setTimezone($timezone)->startOfDay();
                $startDay = $localDay($row->starts_at);
                // One that ends at midnight ends on the day before.
                $endDay = $row->ends_at === null
                    ? $startDay
                    : CarbonImmutable::parse($row->ends_at, 'UTC')->subSecond()->setTimezone($timezone)->startOfDay()->max($startDay);
                $end = $endDay->min($last);

                for ($day = $startDay->max($first); $day <= $end; $day = $day->addDay()) {
                    $days[$day->toDateString()] = true;
                }
            });

        $days = array_keys($days);
        sort($days);

        return ['month' => $first->format('Y-m'), 'days' => $days];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function nearPointFrom(Request $request): ?array
    {
        if (! $request->filled('near_lat') || ! $request->filled('near_lng')) {
            return null;
        }

        $lat = $request->float('near_lat');
        $lng = $request->float('near_lng');

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [$lat, $lng];
    }

    /**
     * @return Collection<int, int>
     */
    private function followedIds(User $user): Collection
    {
        return $user->following()->wherePivot('status', FollowStatus::Accepted->value)->pluck('users.id');
    }

    /**
     * Narrows a feed query by the viewer's relationship to the event, by
     * organizer (friends, followed users, specific people) and by contact
     * group, as the list and calendar pages share.
     *
     * @param  Builder<Event>  $query
     * @param  Collection<int, int>  $followedIds
     * @return Builder<Event>
     */
    private function applyContactFilters(Builder $query, Request $request, User $user, Collection $followedIds): Builder
    {
        // The viewer's own relationship to the event: invited and not yet
        // answered, going (or maybe), or hosting.
        match ($request->string('mine')->toString()) {
            'invited' => $query->whereHas('attendees', fn (Builder $attendees) => $attendees
                ->where('user_id', $user->id)
                ->where('source', AttendeeSource::Invited)
                ->where('status', AttendanceStatus::NoResponse)),
            'going' => $query->whereHas('attendees', fn (Builder $attendees) => $attendees
                ->where('user_id', $user->id)
                ->whereIn('status', [AttendanceStatus::Attending, AttendanceStatus::Interested])),
            'hosting' => $query->where(fn (Builder $hosting) => $hosting
                ->where('user_id', $user->id)
                ->orWhereIn('organizer_id', $user->organizerIds()->all())),
            default => null,
        };

        $ownerFilter = $request->array('owner_filter');
        if ($ownerFilter !== []) {
            $allowedOwnerIds = collect();

            if (in_array('friends', $ownerFilter, true)) {
                $allowedOwnerIds = $allowedOwnerIds->merge($user->friendIds());
            }

            if (in_array('following', $ownerFilter, true)) {
                $allowedOwnerIds = $allowedOwnerIds->merge($followedIds);
            }

            $specificIds = collect($ownerFilter)->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (int) $value);

            $query->whereIn('user_id', $allowedOwnerIds->merge($specificIds)->unique()->all());
        }

        if ($request->filled('group_id')) {
            $group = ContactGroup::query()->find($request->integer('group_id'));

            if ($group !== null && $user->is($group->owner)) {
                $query->whereIn('user_id', DB::table('contact_group_members')
                    ->select('member_user_id')
                    ->where('contact_group_id', $group->id));
            }
        }

        return $query;
    }

    /**
     * The options the filter panel offers: categories, contacts and groups.
     *
     * @param  Collection<int, int>  $followedIds
     * @return array<string, mixed>
     */
    private function filterPanelProps(User $user, Collection $followedIds): array
    {
        return [
            'categories' => Tag::categoriesTree(),
            'friends' => $user->friends()->map(fn (User $friend) => ['id' => $friend->id, 'name' => $friend->name, 'username' => $friend->username, 'avatar_url' => $friend->avatar_url])->values(),
            'following' => User::query()->whereIn('id', $followedIds)->orderBy('name')->get(['id', 'name', 'username', 'avatar_path']),
            'friendGroups' => $user->contactGroups()->ofType(ContactGroupType::Friend)->get(['id', 'name']),
            'followingGroups' => $user->contactGroups()->ofType(ContactGroupType::Following)->get(['id', 'name']),
        ];
    }

    /**
     * A `YYYY-MM-DD` value from the query string, or '' when absent or
     * malformed — a bad date narrows nothing rather than erroring.
     */
    private function dateFrom(Request $request, string $key): string
    {
        $value = $request->string($key)->toString();

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return '';
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : '';
    }

    /** Today's date (YYYY-MM-DD) in the display time zone. */
    private function today(): string
    {
        return CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();
    }

    /**
     * @param  LengthAwarePaginator<int, Event>  $events
     * @return array{current_page: int, last_page: int, total: int, previous_url: string|null, next_url: string|null}
     */
    private function summarizePagination(LengthAwarePaginator $events): array
    {
        return [
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'previous_url' => $events->previousPageUrl(),
            'next_url' => $events->nextPageUrl(),
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  array<int>  $tagIds
     */
    private function syncTags(Event $event, array $tagIds): void
    {
        $tagIds = collect($tagIds)->unique();

        $parentIds = Tag::query()->whereIn('id', $tagIds)->whereNotNull('parent_id')->pluck('parent_id');

        $event->tags()->sync($tagIds->merge($parentIds)->unique());
    }

    /**
     * Replaces the event's image with an upload, or, without one, applies a
     * changed "fill the frame / show it whole" choice to the current image
     * (only when the form sent one). `$fit` is 'cover', 'contain', or null
     * to let the image's shape decide.
     */
    private function storeImage(Event $event, ?UploadedFile $file, ?string $fit, bool $fitSent = true): void
    {
        $fit = in_array($fit, ['cover', 'contain'], true) ? $fit : null;

        if ($file === null) {
            if ($fitSent) {
                $event->images()->update(['fit' => $fit]);
            }

            return;
        }

        foreach ($event->images as $existingImage) {
            Storage::disk('public')->delete($existingImage->path);
            $existingImage->delete();
        }

        $stored = $this->imageProcessor->storeWithSize($file, "events/{$event->id}", ImageProcessor::EVENT_MAX_EDGE);

        $event->images()->create([
            'path' => $stored['path'],
            'position' => 0,
            'width' => $stored['width'],
            'height' => $stored['height'],
            'fit' => $fit,
        ]);
    }
}
