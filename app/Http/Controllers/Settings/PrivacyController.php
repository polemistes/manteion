<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePrivacySettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrivacyController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Privacy', [
            'privacy' => $request->user()->only([
                'allow_public_follow',
                'show_friends_list',
                'appear_in_friends_lists',
                'searchable',
            ]),
        ]);
    }

    public function update(UpdatePrivacySettingsRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
