<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventStartingPoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventTemplateFromEventController extends Controller
{
    /**
     * Makes a template of an event its host picked, and opens it for
     * editing: the host sees what came along and can tidy it.
     */
    public function __invoke(Request $request, Event $event, EventStartingPoints $startingPoints): RedirectResponse
    {
        Gate::authorize('update', $event);

        $template = $startingPoints->templateFromEvent($event, $request->user());

        return redirect()->route('event-templates.edit', $template);
    }
}
