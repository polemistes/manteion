<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class EventImageController extends Controller
{
    public function destroy(Event $event, EventImage $image): RedirectResponse
    {
        Gate::authorize('delete', $image);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back();
    }
}
