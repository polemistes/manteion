<?php

namespace App\Http\Controllers;

use App\Events\BadgesUpdated;
use App\Services\ActivityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * The Activity page. Opening it counts as having seen everything on it:
     * the items still say what was unread when the page loaded, but they are
     * all read from then on. A partial reload (a live update), a prefetch or
     * an administrator browsing as the user marks nothing.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $notifications = ActivityFeed::for($user);

        if (ActivityFeed::seenByUser($request)) {
            ActivityFeed::markRead($user);
        }

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function update(Request $request, string $notification): RedirectResponse|HttpResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();

            BadgesUpdated::dispatch($user->id);
        }

        return $request->wantsJson() ? response()->noContent() : back();
    }
}
