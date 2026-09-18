<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictImpersonatedRequests
{
    /**
     * While an administrator is impersonating another user, they may browse
     * freely (GET/HEAD) and delete that user's content, but nothing else —
     * they cannot post, change settings, or otherwise act as the user.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'admin.impersonate.exit',
        'events.destroy',
        'events.comments.destroy',
        'events.images.destroy',
        'messages.messages.destroy',
        'leaflets.destroy',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('impersonator_id')) {
            return $next($request);
        }

        if ($request->isMethod('get') || $request->isMethod('head')) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        abort(403);
    }
}
