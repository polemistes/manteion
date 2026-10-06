<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Following an organizer needs no approval: organizers are public.
 */
class OrganizerFollowController extends Controller
{
    public function store(Request $request, Organizer $organizer): RedirectResponse
    {
        $organizer->followers()->syncWithoutDetaching([$request->user()->id]);
        $request->user()->forgetOrganizerIds();

        return back();
    }

    public function destroy(Request $request, Organizer $organizer): RedirectResponse
    {
        $organizer->followers()->detach($request->user()->id);
        $request->user()->forgetOrganizerIds();

        return back();
    }
}
