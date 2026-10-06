<?php

namespace App\Http\Controllers;

use App\Enums\SpokespersonRole;
use App\Events\BadgesUpdated;
use App\Models\Organizer;
use App\Models\OrganizerSpokesperson;
use App\Models\User;
use App\Notifications\SpokespersonInvited;
use App\Services\ActivityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Who speaks for an organizer. The main spokesperson invites, removes and
 * hands over; an invited person accepts or declines in Activity; anyone
 * may leave, or choose whether to be listed on the page.
 */
class OrganizerSpokespersonController extends Controller
{
    /**
     * The main spokesperson invites one of their friends. The invitation
     * says what being a spokesperson means (see the Activity row).
     */
    public function store(Request $request, Organizer $organizer): RedirectResponse
    {
        Gate::authorize('update', $organizer);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $invitee = User::query()->findOrFail((int) $validated['user_id']);

        if (! $request->user()->isFriendsWith($invitee)) {
            throw ValidationException::withMessages(['user_id' => __('messages.organizer.friends_only')]);
        }

        if ($organizer->membershipOf($invitee) !== null) {
            throw ValidationException::withMessages(['user_id' => __('messages.organizer.already_in')]);
        }

        $membership = $organizer->memberships()->create([
            'user_id' => $invitee->id,
            'role' => SpokespersonRole::Spokesperson,
            'invited_by_user_id' => $request->user()->id,
        ]);

        $invitee->notify((new SpokespersonInvited($membership, $request->user()))->locale($invitee->locale ?? 'en'));
        BadgesUpdated::dispatch($invitee->id);

        return back();
    }

    /**
     * Answers and changes on one membership: the invited person accepts or
     * declines; a spokesperson chooses whether to be listed; the main
     * spokesperson hands the role on.
     */
    public function update(Request $request, Organizer $organizer, User $user): RedirectResponse
    {
        $membership = $organizer->membershipOf($user);
        abort_if($membership === null, 404);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'decline', 'make_main'])],
            'listed' => ['sometimes', 'boolean:strict'],
        ]);

        $actor = $request->user();

        if (array_key_exists('listed', $validated)) {
            abort_unless($actor->is($user) && ! $membership->isPending(), 403);
            $membership->update(['listed' => $validated['listed']]);
        }

        match ((string) $validated['action']) {
            'accept' => $this->answer($actor, $membership, true),
            'decline' => $this->answer($actor, $membership, false),
            'make_main' => $this->handOver($actor, $organizer, $membership),
            default => abort(422),
        };

        return back();
    }

    /**
     * The main spokesperson removes someone; anyone but the main
     * spokesperson leaves. The main spokesperson hands over first (or
     * deletes the organizer if alone).
     */
    public function destroy(Request $request, Organizer $organizer, User $user): RedirectResponse
    {
        $membership = $organizer->membershipOf($user);
        abort_if($membership === null, 404);

        $actor = $request->user();
        $leaving = $actor->is($user);
        abort_unless($leaving || $organizer->isMainSpokesperson($actor), 403);

        if ($membership->role === SpokespersonRole::Main && ! $membership->isPending()) {
            throw ValidationException::withMessages(['role' => __('messages.organizer.hand_over_first')]);
        }

        // The events and leaflets they made for the organizer stay its own,
        // in the main spokesperson's name.
        $main = $organizer->mainSpokesperson();
        if ($main !== null) {
            $organizer->events()->where('user_id', $user->id)->update(['user_id' => $main->id]);
            $organizer->leaflets()->where('user_id', $user->id)->update(['user_id' => $main->id]);
        }

        if ($membership->isPending()) {
            ActivityFeed::withdraw($user, SpokespersonInvited::class, 'spokesperson_id', $membership->id);
        }

        $membership->delete();
        $user->forgetOrganizerIds();
        BadgesUpdated::dispatch($user->id);

        return back();
    }

    private function answer(User $actor, OrganizerSpokesperson $membership, bool $accepted): void
    {
        abort_unless($actor->id === $membership->user_id && $membership->isPending(), 403);

        if ($accepted) {
            $membership->update(['accepted_at' => now()]);
        } else {
            // A decline is quiet: the invitation simply goes.
            ActivityFeed::withdraw($actor, SpokespersonInvited::class, 'spokesperson_id', $membership->id);
            $membership->delete();
        }

        ActivityFeed::markRead($actor, 'organizer', $membership->organizer_id);
        $actor->forgetOrganizerIds();
        BadgesUpdated::dispatch($actor->id);
    }

    private function handOver(User $actor, Organizer $organizer, OrganizerSpokesperson $membership): void
    {
        abort_unless($organizer->isMainSpokesperson($actor), 403);

        if ($membership->isPending() || $membership->user_id === $actor->id) {
            throw ValidationException::withMessages(['action' => __('messages.organizer.hand_over_to_member')]);
        }

        DB::transaction(function () use ($organizer, $actor, $membership): void {
            $organizer->memberships()->where('user_id', $actor->id)->update(['role' => SpokespersonRole::Spokesperson]);
            $membership->update(['role' => SpokespersonRole::Main]);
        });
    }
}
