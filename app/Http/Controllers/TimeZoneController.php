<?php

namespace App\Http\Controllers;

use App\Services\TimeZones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimeZoneController extends Controller
{
    /**
     * Remembers the time zone of the device the person is using, so the
     * server writes times, and counts "today", as their clock does. The
     * app reports it by itself whenever it differs from what is stored.
     */
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', 'max:64', 'timezone:all_with_bc'],
        ]);

        $request->user()->update(['timezone' => $validated['timezone']]);

        return response()->noContent();
    }

    /**
     * The time zone at a map pin, for the event form: the times typed
     * there are local to the place.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
        ]);

        return response()->json([
            'timezone' => TimeZones::forPlace((float) $validated['latitude'], (float) $validated['longitude'], $validated['country_code'] ?? null),
        ]);
    }
}
