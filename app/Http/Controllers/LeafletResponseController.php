<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreLeafletResponseRequest;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeafletResponseController extends Controller
{
    public function store(StoreLeafletResponseRequest $request, Leaflet $leaflet): RedirectResponse
    {
        $leaflet->responses()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

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

        return back();
    }
}
