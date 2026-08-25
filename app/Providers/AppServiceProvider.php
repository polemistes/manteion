<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // A long passphrase resists brute-force attacks better than a short
        // password padded with mandatory character classes, without the
        // usability cost — see NIST SP 800-63B. The breach check hits an
        // external API, so it's skipped outside production to keep tests
        // and local dev offline.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(10)->uncompromised()
            : Password::min(10),
        );
    }
}
