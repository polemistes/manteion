<?php

namespace App\Http\Controllers;

use App\Enums\SpokespersonRole;
use App\Http\Requests\StoreOrganizerRequest;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\OrganizerSpokesperson;
use App\Models\User;
use App\Services\ActivityFeed;
use App\Services\EventFeedQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerController extends Controller
{
    private const UPCOMING_EVENTS = 10;

    public function create(): Response
    {
        return Inertia::render('organizers/Create');
    }

    /**
     * The person who creates an organizer is its main spokesperson.
     */
    public function store(StoreOrganizerRequest $request): RedirectResponse
    {
        $organizer = DB::transaction(function () use ($request): Organizer {
            $organizer = Organizer::query()->create([
                ...$request->validated(),
                'created_by' => $request->user()->id,
            ]);

            $organizer->memberships()->create([
                'user_id' => $request->user()->id,
                'role' => SpokespersonRole::Main,
                'accepted_at' => now(),
            ]);

            return $organizer;
        });

        $request->user()->forgetOrganizerIds();

        return redirect()->route('organizers.show', $organizer);
    }

    /**
     * The organizer's page: who it is, what it has coming up, who speaks
     * for it, and the viewer's standing with it.
     */
    public function show(Request $request, Organizer $organizer): Response
    {
        $viewer = $request->user();
        $membership = $organizer->membershipOf($viewer);
        $isSpokesperson = $membership !== null && ! $membership->isPending();
        $isMain = $isSpokesperson && $membership->role === SpokespersonRole::Main;

        // Opening the page means its invitation has been seen.
        if (ActivityFeed::seenByUser($request)) {
            ActivityFeed::markRead($viewer, 'organizer', $organizer->id);
        }

        $feed = new EventFeedQuery($viewer);
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();
        $events = $feed->happeningBetween($feed->ofOrganizer($organizer), $today, null)
            ->orderBy('starts_at')
            ->limit(self::UPCOMING_EVENTS)
            ->get();

        // Spokespersons see the whole team, invitations included; others
        // only those who chose to be listed.
        $memberships = $organizer->memberships()->with('user')->orderByDesc('role')->orderBy('id')->get()
            ->filter(fn (OrganizerSpokesperson $member) => $isSpokesperson || ($member->listed && ! $member->isPending()));

        return Inertia::render('organizers/Show', [
            'organizer' => [
                ...$organizer->summary(),
                'description' => $organizer->description,
                'followers_count' => $organizer->followers()->count(),
            ],
            'is_following' => $organizer->isFollowedBy($viewer),
            'my_role' => $isSpokesperson ? $membership->role->value : null,
            'my_invitation_id' => $membership !== null && $membership->isPending() ? $membership->id : null,
            'my_listed' => $membership?->listed,
            'can_update' => Gate::allows('update', $organizer),
            'can_delete' => Gate::allows('delete', $organizer),
            'spokespersons' => $memberships->map(fn (OrganizerSpokesperson $member) => [
                'id' => $member->id,
                'user' => ['id' => $member->user->id, 'name' => $member->user->name, 'username' => $member->user->username, 'avatar_url' => $member->user->avatar_url],
                'role' => $member->role->value,
                'listed' => $member->listed,
                'pending' => $member->isPending(),
            ])->values()->all(),
            // Whom the main spokesperson may invite: their friends who are
            // not already in.
            'inviteCandidates' => ! $isMain ? [] : $viewer->friends()
                ->reject(fn (User $friend) => $memberships->contains(fn (OrganizerSpokesperson $member) => $member->user_id === $friend->id))
                ->map(fn (User $friend) => ['id' => $friend->id, 'name' => $friend->name, 'username' => $friend->username, 'avatar_url' => $friend->avatar_url])
                ->values()->all(),
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'place' => $event->place,
                'city' => $event->city,
                'cover' => $event->images->first()?->presentation(),
                'my_status' => $event->attendees->first()?->status->value,
                'is_host' => $isSpokesperson,
            ])->all(),
        ]);
    }

    public function edit(Organizer $organizer): Response
    {
        Gate::authorize('update', $organizer);

        return Inertia::render('organizers/Edit', [
            'organizer' => [
                ...$organizer->summary(),
                'description' => $organizer->description,
            ],
        ]);
    }

    public function update(StoreOrganizerRequest $request, Organizer $organizer): RedirectResponse
    {
        Gate::authorize('update', $organizer);

        $organizer->update($request->validated());

        return redirect()->route('organizers.show', $organizer);
    }

    /**
     * Deleting an organizer takes its events and leaflets with it (the
     * database cascades), and everyone's Activity about them.
     */
    public function destroy(Request $request, Organizer $organizer): RedirectResponse
    {
        Gate::authorize('delete', $organizer);

        $organizer->events()->pluck('id')->each(fn (int $id) => ActivityFeed::withdrawAbout('event', $id));
        $organizer->leaflets()->pluck('id')->each(fn (int $id) => ActivityFeed::withdrawAbout('leaflet', $id));
        ActivityFeed::withdrawAbout('organizer', $organizer->id);

        $organizer->delete();
        $request->user()->forgetOrganizerIds();

        return redirect()->route('settings.organizers');
    }
}
