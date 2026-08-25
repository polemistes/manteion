<?php

namespace App\Http\Controllers;

use App\Enums\EventVisibility;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EventSearchabilityController extends Controller
{
    public function store(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        $event->update([
            'visibility' => EventVisibility::Searchable,
            'searchable_paid_at' => now(),
        ]);

        return back();
    }
}
