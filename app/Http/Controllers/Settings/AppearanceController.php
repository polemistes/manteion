<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ThemeMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAppearanceSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /**
     * Light, dark or following the system. The language is switched with
     * the existing PUT /locale.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Appearance', [
            'theme_mode' => ($request->user()->theme_mode ?? ThemeMode::System)->value,
        ]);
    }

    public function update(UpdateAppearanceSettingsRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
