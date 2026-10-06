<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreLeafletResponseRequest;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\User;
use App\Notifications\NewLeafletResponse;
use App\Services\ActivityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeafletResponseController extends Controller
{
    public function store(StoreLeafletResponseRequest $request, Leaflet $leaflet): RedirectResponse
    {
        $response = $leaflet->responses()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        // The author hears about everyone's replies but their own: the
        // writer, or every spokesperson of the organizer it is from.
        $leaflet->authors()
            ->reject(fn (User $author) => $author->id === $response->user_id)
            ->each(fn (User $author) => $author->notify((new NewLeafletResponse($response))->locale($author->locale ?? 'en')));

        return back();
    }

    public function destroy(Request $request, Leaflet $leaflet, LeafletResponse $response): RedirectResponse
    {
        Gate::authorize('delete', $response);

        $response->reports()->where('status', ReportStatus::Pending)->update([
            'status' => ReportStatus::Resolved,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $response->delete();

        // The authors' notifications quote the reply; they go with it.
        $leaflet->authors()->each(fn (User $author) => ActivityFeed::withdraw($author, NewLeafletResponse::class, 'response_id', $response->id));

        return back();
    }
}
