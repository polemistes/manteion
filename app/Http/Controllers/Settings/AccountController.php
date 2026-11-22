<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\OrganizerDepartures;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Name, username, email, bio and photo, the password and account
     * deletion. Saving goes through Fortify's profile-information endpoint,
     * the password endpoint and DELETE /profile. Someone who speaks for
     * organizers decides, when deleting the account, what becomes of what
     * they made for each (`organizers`, see OrganizerDepartures).
     */
    public function edit(Request $request, OrganizerDepartures $departures): Response
    {
        return Inertia::render('settings/Account', [
            'user' => $request->user()->only(['name', 'username', 'email', 'bio', 'avatar_url']),
            'organizers' => $departures->choicesFor($request->user()),
        ]);
    }
}
