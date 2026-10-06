<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OrganizerSpokesperson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organizers the user speaks for, and invitations waiting.
 */
class OrganizerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Organizers', [
            'organizers' => OrganizerSpokesperson::query()
                ->where('user_id', $user->id)
                ->whereNotNull('accepted_at')
                ->with('organizer')
                ->get()
                ->sortBy(fn (OrganizerSpokesperson $membership) => $membership->organizer->name)
                ->map(fn (OrganizerSpokesperson $membership) => [
                    ...$membership->organizer->summary(),
                    'role' => $membership->role->value,
                    'listed' => $membership->listed,
                    'events_count' => $membership->organizer->events()->where('is_deleted', false)->count(),
                ])->values()->all(),
            'invitations' => $user->spokespersonInvitations()->with(['organizer', 'invitedBy'])->get()
                ->map(fn (OrganizerSpokesperson $invitation) => [
                    'id' => $invitation->id,
                    'organizer' => $invitation->organizer->summary(),
                    'invited_by' => $invitation->invitedBy?->name,
                ])->values()->all(),
        ]);
    }
}
