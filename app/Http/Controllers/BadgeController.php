<?php

namespace App\Http\Controllers;

use App\Services\BadgeCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    /**
     * The current badge counts, for a client catching up (window focus, a
     * WebSocket reconnect) without a page visit.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(BadgeCounts::for($request->user()));
    }
}
