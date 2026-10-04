<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Name, username, email, bio and photo, the password and account
     * deletion. Saving goes through Fortify's profile-information endpoint,
     * the password endpoint and DELETE /profile.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Account', [
            'user' => $request->user()->only(['name', 'username', 'email', 'bio', 'avatar_url']),
        ]);
    }
}
