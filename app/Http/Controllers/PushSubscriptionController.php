<?php

namespace App\Http\Controllers;

use App\Enums\PushKind;
use App\Models\PushSubscription;
use App\Models\User;
use App\Rules\PushServiceEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Minishlink\WebPush\ContentEncoding;

/**
 * Each push subscription is one device (a browser, or the Android app) that
 * gets the user's push notifications.
 */
class PushSubscriptionController extends Controller
{
    /** How many devices one account can get pushes on. */
    public const MAX_DEVICES = 10;

    /** What an FCM registration token looks like: URL-safe base64 and colons. */
    public const FCM_TOKEN_PATTERN = '/^[A-Za-z0-9_:\-]{20,1024}$/';

    /**
     * Register this browser or app install as one of the user's devices, or
     * refresh it. A browser sends its push service endpoint and keys; the
     * Android app sends `kind` fcm and its registration token as the
     * endpoint.
     */
    public function store(Request $request): RedirectResponse|Response
    {
        $kind = $request->enum('kind', PushKind::class) ?? PushKind::WebPush;

        $request->validate([
            'kind' => ['nullable', Rule::enum(PushKind::class)],
            ...($kind === PushKind::Fcm ? [
                // An FCM token, never a URL: the server only ever hands it to
                // Google's FCM API, so it needs no host check.
                'endpoint' => ['required', 'string', 'regex:'.self::FCM_TOKEN_PATTERN],
                'key' => ['prohibited'],
                'token' => ['prohibited'],
                'content_encoding' => ['prohibited'],
            ] : [
                'endpoint' => ['required', 'string', 'max:1024', new PushServiceEndpoint],
                'key' => ['nullable', 'string', 'max:255'],
                'token' => ['nullable', 'string', 'max:255'],
                'content_encoding' => ['nullable', Rule::enum(ContentEncoding::class)],
            ]),
        ]);

        $user = $request->user();
        $endpoint = $request->string('endpoint')->toString();

        $subscription = $kind === PushKind::Fcm
            ? $this->registerApp($user, $endpoint)
            : $user->updatePushSubscription(
                $endpoint,
                $request->input('key'),
                $request->input('token'),
                $request->input('content_encoding'),
            );

        // So the notification settings can name the device ("Firefox on
        // Windows", "Manteion app on Android"); subscribing again from the
        // same device refreshes it.
        $subscription->forceFill([
            'kind' => $kind,
            'user_agent' => Str::substr((string) $request->userAgent(), 0, 512) ?: null,
        ])->save();

        // Every notification goes to every device, so keep the newest few.
        $stale = $user->pushSubscriptions()->orderByDesc('id')->skip(self::MAX_DEVICES)->take(PHP_INT_MAX)->pluck('id');

        if ($stale->isNotEmpty()) {
            $user->pushSubscriptions()->whereKey($stale)->delete();
        }

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
    /**
     * The app install with this token, now the user's. A token that was
     * another account's (someone else signed in on that phone before)
     * moves over, so the previous account stops getting pushes there.
     */
    private function registerApp(User $user, string $token): PushSubscription
    {
        $existing = PushSubscription::query()->where('endpoint', $token)->first();

        if ($existing !== null && $user->ownsPushSubscription($existing)) {
            return $existing;
        }

        $existing?->delete();

        /** @var PushSubscription */
        return $user->pushSubscriptions()->create(['endpoint' => $token, 'kind' => PushKind::Fcm]);
    }

    private function done(Request $request): RedirectResponse|Response
    {
        if ($request->wantsJson() || ($request->isJson() && ! $request->hasHeader('X-Inertia'))) {
            return response()->noContent();
        }

        return back();
    }
}
