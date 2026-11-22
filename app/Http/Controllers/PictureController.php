<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesPrivateImages;
use App\Models\EventComment;
use App\Models\Leaflet;
use App\Models\Message;
use App\Models\Picture;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PictureController extends Controller
{
    use ServesPrivateImages;

    /**
     * A picture on a message, a leaflet or an event comment, at full size
     * or as its smaller copy, for whoever may see what it is on: the
     * people in the conversation, the leaflet's readers, the event's
     * guests (and staff, as for those pages). The address names the file,
     * so a deleted picture never comes back under it.
     */
    public function show(Picture $picture, string $file): StreamedResponse
    {
        $owner = $picture->message ?? $picture->leaflet ?? $picture->comment;

        Gate::authorize('view', match (true) {
            $owner instanceof Message => $owner->conversation,
            $owner instanceof Leaflet => $owner,
            $owner instanceof EventComment => $owner->event,
            default => abort(404),
        });

        abort_unless(in_array($file, array_map(basename(...), $picture->files()), true), 404);

        return $this->privateImage($picture->thumb_path !== null && basename($picture->thumb_path) === $file ? $picture->thumb_path : $picture->path);
    }
}
