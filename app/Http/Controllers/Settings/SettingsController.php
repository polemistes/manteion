<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * The settings hub: the list of sections, plus the staff tools for
     * moderators and administrators (from the shared `auth.user`).
     */
    public function index(): Response
    {
        return Inertia::render('settings/Index');
    }

    /**
     * English or Norwegian. The choice is saved with PUT /locale; the
     * current and available locales are shared with every page.
     */
    public function language(): Response
    {
        return Inertia::render('settings/Language');
    }
}
