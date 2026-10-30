<?php

namespace App\Http\Controllers;

use App\Events\EventActivity;
use App\Http\Controllers\Concerns\ServesPrivateImages;
use App\Models\Event;
use App\Models\EventImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventImageController extends Controller
{
    use ServesPrivateImages;

    /**
     * An event's picture, for whoever may see the event (and staff). The
     * address names the file as well as the image, so a picture replaced
     * in place is never mistaken for the old one.
     */
    public function show(Event $event, EventImage $image, string $file): StreamedResponse
    {
        Gate::authorize('view', $event);

        abort_unless(basename($image->path) === $file, 404);

        return $this->privateImage($image->path);
    }

    public function destroy(Event $event, EventImage $image): RedirectResponse
    {
        Gate::authorize('delete', $image);

        $image->deleteFile();
        $image->delete();

        broadcast(new EventActivity($event->id, 'updated'))->toOthers();

        return back();
    }
}
