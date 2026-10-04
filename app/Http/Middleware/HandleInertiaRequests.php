<?php

namespace App\Http\Middleware;

use App\Enums\ThemeMode;
use App\Models\User;
use App\Services\BadgeCounts;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            // A closure, so a partial reload that doesn't ask for the badges
            // (a filter change, loading more) doesn't run their queries.
            'badges' => fn () => $user === null ? BadgeCounts::none() : BadgeCounts::for($user),
            'locale' => app()->getLocale(),
            'theme' => self::themeFor($user),
            'availableLocales' => config('app.supported_locales'),
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'impersonating' => $request->session()->has('impersonator_id'),
        ];
    }

    /**
     * The light/dark mode to render for a user, following the system for
     * guests. Used both here (so the client can switch without a reload) and
     * by the root Blade view (so the first paint is already right).
     *
     * @return array{mode: string}
     */
    public static function themeFor(?User $user): array
    {
        return [
            'mode' => ($user->theme_mode ?? ThemeMode::System)->value,
        ];
    }
}
