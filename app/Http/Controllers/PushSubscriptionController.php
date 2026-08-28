<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
            'key' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $request->string('endpoint')->toString(),
            $request->input('key'),
            $request->input('token'),
            $request->input('content_encoding'),
        );

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->deletePushSubscription($request->string('endpoint')->toString());

        return back();
    }
}
