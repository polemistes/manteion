<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ContactGroupType;
use App\Enums\EventVisibility;
use App\Enums\FollowStatus;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\ContactGroup;
use App\Models\ContactTagPreference;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventComment;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\FollowedUserPublishedEvent;
use App\Services\ImageProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private ImageProcessor $imageProcessor) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $events = $this->visibleEventsFor($user);

        $tagFilter = $request->array('tags');
        if ($tagFilter !== []) {
            $events = $events->filter(fn (Event $event) => $event->tags->pluck('slug')->intersect($tagFilter)->isNotEmpty());
        }

        $friendIds = $user->friends()->pluck('id');
        $followedIds = $user->following()->wherePivot('status', FollowStatus::Accepted->value)->pluck('users.id');

        $ownerFilter = $request->array('owner_filter');
        if ($ownerFilter !== []) {
            $allowedOwnerIds = collect();

            if (in_array('friends', $ownerFilter, true)) {
                $allowedOwnerIds = $allowedOwnerIds->merge($friendIds);
            }

            if (in_array('following', $ownerFilter, true)) {
                $allowedOwnerIds = $allowedOwnerIds->merge($followedIds);
            }

            $specificIds = collect($ownerFilter)->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (int) $value);
            $allowedOwnerIds = $allowedOwnerIds->merge($specificIds)->unique();

            $events = $events->filter(fn (Event $event) => $allowedOwnerIds->contains($event->user_id));
        }

        if ($request->filled('group_id')) {
            $group = ContactGroup::query()->find($request->integer('group_id'));

            if ($group !== null && $user->is($group->owner)) {
                $memberIds = $group->members()->pluck('users.id');
                $events = $events->filter(fn (Event $event) => $memberIds->contains($event->user_id));
            }
        }

        $startDateFilter = $request->has('start_date') ? $request->string('start_date')->toString() : now()->toDateString();
        if ($startDateFilter !== '') {
            $events = $events->filter(fn (Event $event) => $event->starts_at->toDateString() >= $startDateFilter);
        }

        if ($request->filled('end_date')) {
            $endDateFilter = $request->string('end_date')->toString();
            $events = $events->filter(fn (Event $event) => $event->starts_at->toDateString() <= $endDateFilter);
        }

        $nearPoint = $this->nearPointFrom($request);

        if ($nearPoint !== null) {
            $events = $this->sortByDistance($events, $nearPoint);
        }

        return Inertia::render('events/Index', [
            'events' => $this->summarizeEvents($events, $user, $nearPoint),
            'categories' => $this->categoriesTree(),
            'friends' => $user->friends()->map(fn (User $friend) => ['id' => $friend->id, 'name' => $friend->name, 'username' => $friend->username, 'avatar_url' => $friend->avatar_url])->values(),
            'following' => User::query()->whereIn('id', $followedIds)->orderBy('name')->get(['id', 'name', 'username', 'avatar_path']),
            'friendGroups' => $user->contactGroups()->ofType(ContactGroupType::Friend)->get(['id', 'name']),
            'followingGroups' => $user->contactGroups()->ofType(ContactGroupType::Following)->get(['id', 'name']),
            'filters' => [
                ...$request->only(['tags', 'owner_filter', 'group_id', 'near_lat', 'near_lng']),
                'start_date' => $startDateFilter,
                'end_date' => $request->filled('end_date') ? $request->string('end_date')->toString() : '',
            ],
        ]);
    }

    public function search(Request $request): Response
    {
        $term = trim((string) $request->string('q'));
        $tagFilter = $request->array('tags');
        $viewer = $request->user();
        $nearPoint = $this->nearPointFrom($request);

        $events = $this->withAttendanceCounts(
            Event::query()
                ->where('visibility', EventVisibility::Searchable)
                ->when($term !== '', fn ($query) => $query->where(
                    fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('place', 'like', "%{$term}%")
                ))
                ->with(['owner', 'tags', 'images', 'attendees' => fn ($query) => $query->where('user_id', $viewer->id)])
        )
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        if ($tagFilter !== []) {
            $events = $events->filter(fn (Event $event) => $event->tags->pluck('slug')->intersect($tagFilter)->isNotEmpty());
        }

        if ($nearPoint !== null) {
            $events = $this->sortByDistance($events, $nearPoint);
        }

        return Inertia::render('events/Search', [
            'q' => $term,
            'categories' => $this->categoriesTree(),
            'events' => $this->summarizeEvents($events, $viewer, $nearPoint),
            'filters' => $request->only(['tags', 'near_lat', 'near_lng']),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('events/Create', [
            'categories' => $this->categoriesTree(),
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = $request->user()->events()->create($request->safe()->only([
            'name', 'place', 'latitude', 'longitude', 'city', 'country', 'description', 'starts_at', 'ends_at', 'visibility', 'allow_invitee_invites', 'allow_invitee_comments',
        ]));

        $this->syncTags($event, $request->input('tag_ids', []));
        $this->storeImage($event, $request->file('image'));

        if ($event->visibility !== EventVisibility::Private) {
            $this->notifyFollowers($event);
        }

        return redirect()->route('events.show', $event);
    }

    public function show(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);

        $viewer = $request->user();
        $event->load(['owner', 'tags', 'images', 'comments.user', 'attendees.user']);

        $isOwner = $viewer->is($event->owner);
        $attendee = $event->attendeeFor($viewer);

        return Inertia::render('events/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'place' => $event->place,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'city' => $event->city,
                'country' => $event->country,
                'description' => $event->description,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'visibility' => $event->visibility->value,
                'allow_invitee_invites' => $event->allow_invitee_invites,
                'allow_invitee_comments' => $event->allow_invitee_comments,
                'is_searchable_paid' => $event->searchable_paid_at !== null,
                'owner' => ['id' => $event->owner->id, 'name' => $event->owner->name, 'username' => $event->owner->username, 'avatar_url' => $event->owner->avatar_url],
                'tags' => $event->tags->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])->values(),
                'images' => $event->images->map(fn ($image) => Storage::disk('public')->url($image->path))->values(),
                'is_owner' => $isOwner,
                'can_invite' => Gate::allows('invite', $event),
                'can_comment' => Gate::allows('comment', $event),
            ],
            'myAttendance' => $attendee === null ? null : [
                'status' => $attendee->status->value,
                'attendance_visibility' => $attendee->attendance_visibility->value,
            ],
            'attendees' => $event->attendees->map(function (EventAttendee $attendee) use ($viewer, $isOwner) {
                $showStatus = $isOwner || $viewer->is($attendee->user) || $attendee->isVisibleTo($viewer);

                return [
                    'id' => $attendee->id,
                    'user' => ['id' => $attendee->user->id, 'name' => $attendee->user->name, 'username' => $attendee->user->username, 'avatar_url' => $attendee->user->avatar_url],
                    'status' => $showStatus ? $attendee->status->value : null,
                ];
            })->values(),
            'comments' => $event->comments->map(fn (EventComment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at->toIso8601String(),
                'user' => ['id' => $comment->user->id, 'name' => $comment->user->name, 'username' => $comment->user->username, 'avatar_url' => $comment->user->avatar_url],
                'can_delete' => $viewer->can('delete', $comment),
            ])->values(),
            'inviteCandidates' => $viewer->friends()
                ->reject(fn (User $friend) => $event->attendeeFor($friend) !== null)
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
                'description' => $event->description,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'visibility' => $event->visibility->value,
                'is_searchable' => $event->visibility === EventVisibility::Searchable,
                'allow_invitee_invites' => $event->allow_invitee_invites,
                'allow_invitee_comments' => $event->allow_invitee_comments,
                'tag_ids' => $event->tags->pluck('id')->values(),
                'image' => $event->images->first() !== null
                    ? ['id' => $event->images->first()->id, 'url' => Storage::disk('public')->url($event->images->first()->path)]
                    : null,
            ],
            'categories' => $this->categoriesTree(),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $visibility = EventVisibility::from($request->string('visibility')->value());

        if ($event->visibility === EventVisibility::Searchable && $visibility === EventVisibility::Public) {
            $visibility = EventVisibility::Searchable;
        }

        $event->update([
            ...$request->safe()->only(['name', 'place', 'latitude', 'longitude', 'city', 'country', 'description', 'starts_at', 'ends_at', 'allow_invitee_invites', 'allow_invitee_comments']),
            'visibility' => $visibility,
        ]);

        $this->syncTags($event, $request->input('tag_ids', []));
        $this->storeImage($event, $request->file('image'));

        return redirect()->route('events.show', $event);
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        foreach ($event->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $event->delete();

        return redirect()->route('events.index');
    }

    /**
     * @return Collection<int, Event>
     */
    private function visibleEventsFor(User $user): Collection
    {
        $attendeeEventIds = EventAttendee::query()->where('user_id', $user->id)->pluck('event_id');

        $followedIds = $user->following()->wherePivot('status', FollowStatus::Accepted->value)->pluck('users.id');

        $preferencesByContact = ContactTagPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->groupBy('contact_user_id')
            ->map(fn (Collection $rows) => $rows->pluck('tag_id'));

        $withAttendee = fn ($query) => $query->where('user_id', $user->id);

        $owned = $this->withAttendanceCounts(
            Event::query()
                ->where('user_id', $user->id)
                ->with(['owner', 'tags', 'images', 'attendees' => $withAttendee])
        )->get();

        $invited = $this->withAttendanceCounts(
            Event::query()
                ->whereIn('id', $attendeeEventIds)
                ->with(['owner', 'tags', 'images', 'attendees' => $withAttendee])
        )->get();

        $followedPublic = $this->withAttendanceCounts(
            Event::query()
                ->whereIn('user_id', $followedIds)
                ->whereIn('visibility', [EventVisibility::Public->value, EventVisibility::Searchable->value])
                ->with(['owner', 'tags', 'images', 'attendees' => $withAttendee])
        )->get()
            ->filter(function (Event $event) use ($preferencesByContact) {
                $mutedTagIds = $preferencesByContact->get($event->user_id);

                if ($mutedTagIds !== null && $mutedTagIds->contains(null)) {
                    return false;
                }

                return $mutedTagIds === null || $event->tags->pluck('id')->intersect($mutedTagIds)->isEmpty();
            });

        return $owned->merge($invited)->merge($followedPublic)->unique('id')->sortBy('starts_at')->values();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  array{0: float, 1: float}|null  $nearPoint
     * @return array<int, array<string, mixed>>
     */
    private function summarizeEvents(Collection $events, User $viewer, ?array $nearPoint = null): array
    {
        return $events->map(fn (Event $event) => [
            'id' => $event->id,
            'name' => $event->name,
            'place' => $event->place,
            'city' => $event->city,
            'country' => $event->country,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'starts_at' => $event->starts_at->toIso8601String(),
            'visibility' => $event->visibility->value,
            'owner' => ['id' => $event->owner->id, 'name' => $event->owner->name, 'username' => $event->owner->username, 'avatar_url' => $event->owner->avatar_url],
            'tags' => $event->tags->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])->values()->all(),
            'my_status' => $event->attendeeFor($viewer)?->status->value,
            'my_attendance_visibility' => $event->attendeeFor($viewer)?->attendance_visibility->value,
            'cover_image_url' => $event->images->first() !== null
                ? Storage::disk('public')->url($event->images->first()->path)
                : null,
            'attending_count' => $event->attending_count,
            'interested_count' => $event->interested_count,
            'distance_km' => $nearPoint !== null && $event->latitude !== null && $event->longitude !== null
                ? round($this->haversineKm($nearPoint[0], $nearPoint[1], $event->latitude, $event->longitude), 1)
                : null,
        ])->values()->all();
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
     * @param  Collection<int, Event>  $events
     * @param  array{0: float, 1: float}  $nearPoint
     * @return Collection<int, Event>
     */
    private function sortByDistance(Collection $events, array $nearPoint): Collection
    {
        return $events->sortBy(fn (Event $event) => $event->latitude === null || $event->longitude === null
            ? PHP_FLOAT_MAX
            : $this->haversineKm($nearPoint[0], $nearPoint[1], $event->latitude, $event->longitude))
            ->values();
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
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function withAttendanceCounts(Builder $query): Builder
    {
        return $query
            ->withCount(['attendees as attending_count' => fn ($query) => $query->where('status', AttendanceStatus::Attending)])
            ->withCount(['attendees as interested_count' => fn ($query) => $query->where('status', AttendanceStatus::Interested)]);
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
     * @return array<int, array{id: int, name: string, slug: string, children: array<int, array{id: int, name: string, slug: string}>}>
     */
    private function categoriesTree(): array
    {
        return Tag::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('position')])
            ->orderBy('position')
            ->get()
            ->map(fn (Tag $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'children' => $category->children->map(fn (Tag $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                ])->values()->all(),
            ])->values()->all();
    }

    private function storeImage(Event $event, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }

        foreach ($event->images as $existingImage) {
            Storage::disk('public')->delete($existingImage->path);
            $existingImage->delete();
        }

        $path = $this->imageProcessor->store($file, "events/{$event->id}");

        $event->images()->create(['path' => $path, 'position' => 0]);
    }

    private function notifyFollowers(Event $event): void
    {
        $tagIds = $event->tags->pluck('id');

        $followers = $event->owner->followers()->wherePivot('status', FollowStatus::Accepted->value)->get();

        foreach ($followers as $follower) {
            $mutedTagIds = ContactTagPreference::query()
                ->where('user_id', $follower->id)
                ->where('contact_user_id', $event->user_id)
                ->pluck('tag_id');

            if ($mutedTagIds->contains(null)) {
                continue;
            }

            if ($mutedTagIds->isEmpty() || $mutedTagIds->intersect($tagIds)->isEmpty()) {
                $follower->notify(new FollowedUserPublishedEvent($event));
            }
        }
    }
}
