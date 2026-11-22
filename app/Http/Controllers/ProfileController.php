<?php

namespace App\Http\Controllers;

use App\Enums\AdminAlert;
use App\Services\AdminAlerts;
use App\Services\OrganizerDepartures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * The profile page became the settings pages; old links to it land on
     * their hub.
     */
    public function edit(): RedirectResponse
    {
        return redirect()->route('settings.index');
    }

    public function destroy(Request $request, OrganizerDepartures $departures): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'current_password'],
            // What becomes of what they made for each organizer they
            // speak for, by the organizer's id.
            'organizers' => ['sometimes', 'array'],
            'organizers.*.hand_over' => ['sometimes', 'boolean'],
            'organizers.*.successor_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $user = $request->user();
        $choices = $validated['organizers'] ?? [];

        // A successor named for an organizer has to be one of its other
        // spokespersons; one left unnamed is its longest-serving.
        foreach ($user->organizers()->get() as $organizer) {
            $successorId = $choices[$organizer->id]['successor_id'] ?? null;

            if ($successorId !== null && ! $departures->canSucceed($organizer, $user, (int) $successorId)) {
                throw ValidationException::withMessages(['organizers' => __('messages.organizer.hand_over_to_member')]);
            }
        }

        $departures->leaveAll($user, $choices);
        $user->deleteUploadedFiles();

        Auth::guard('web')->logout();

        $user->delete();

        AdminAlerts::send(AdminAlert::AccountDeleted, $user, [
            'name' => $user->name,
            'username' => $user->username,
            'by' => __('admin_alerts.deleted_by_self'),
        ], route('admin.users.index'));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
