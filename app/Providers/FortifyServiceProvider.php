<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RejectSuspendedUsers;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        // Changing your own password is handled by our own controller/route
        // instead (see Auth/UserPasswordController) — Fortify's stock
        // PasswordController unconditionally deletes a password-reset
        // token in a table that no longer exists after a successful update.

        // Same effective pipeline Fortify uses by default given this app's
        // config (a named login rate limiter, no 2FA), plus RejectSuspendedUsers
        // inserted right after the credential check.
        Fortify::authenticateThrough(fn () => [
            AttemptToAuthenticate::class,
            RejectSuspendedUsers::class,
            PrepareAuthenticatedSession::class,
        ]);

        Fortify::loginView(fn () => Inertia::render('auth/Login'));
        Fortify::registerView(fn () => Inertia::render('auth/Register'));
        // Password reset views/routes are registered in routes/web.php
        // instead (see app/Http/Controllers/Auth/) — the resetPasswords
        // feature is disabled in config/fortify.php.

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('password-email', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->string('email')->value()).'|'.$request->ip());

            return Limit::perMinute(3)->by($throttleKey);
        });
    }
}
