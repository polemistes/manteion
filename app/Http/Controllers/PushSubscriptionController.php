<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Minishlink\WebPush\ContentEncoding;

/**
 * Each push subscription is one device (one browser) that gets the user's
 * push notifications.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse|Response
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
            'key' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
            'content_encoding' => ['nullable', Rule::enum(ContentEncoding::class)],
        ]);

        $subscription = $request->user()->updatePushSubscription(
            $request->string('endpoint')->toString(),
            $request->input('key'),
            $request->input('token'),
            $request->input('content_encoding'),
        );

        // So the notification settings can name the device ("Firefox on
        // Windows"); subscribing again from the same browser refreshes it.
        $subscription->forceFill([
            'user_agent' => Str::substr((string) $request->userAgent(), 0, 512) ?: null,
        ])->save();

        return $this->done($request);
    }

    public function destroy(Request $request): RedirectResponse|Response
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->deletePushSubscription($request->string('endpoint')->toString());

        return $this->done($request);
    }

    /**
     * Remove one of the user's devices from the notification settings. By
     * id rather than endpoint: the page lists the user's other devices too,
     * and a browser only knows its own endpoint.
     */
    public function destroyDevice(Request $request, PushSubscription $subscription): RedirectResponse|Response
    {
        abort_unless($request->user()->ownsPushSubscription($subscription), 403);

        $subscription->delete();

        return $this->done($request);
    }

    /**
     * The push helper and the service worker send plain JSON and only need
     * to know it worked; Inertia visits and forms go back to their page.
     */
    private function done(Request $request): RedirectResponse|Response
    {
        if ($request->wantsJson() || ($request->isJson() && ! $request->hasHeader('X-Inertia'))) {
            return response()->noContent();
        }

        return back();
    }
}
