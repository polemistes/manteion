<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\WebPushChannel;

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
        $this->configureWebPushLogger();
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

    /**
     * Route the webpush package's own advisory notices (e.g. "install GMP
     * or BCMath") through Laravel's logger instead of a raw trigger_error(),
     * which the test environment escalates into a hard failure. Overrides
     * the package's own binding, which doesn't pass a logger.
     */
    protected function configureWebPushLogger(): void
    {
        $this->app->when(WebPushChannel::class)
            ->needs(WebPush::class)
            ->give(function () {
                /** @var array{vapid: array{subject: string|null, public_key: string|null, private_key: string|null}, client_options: array<mixed>, automatic_padding: bool|int} $config */
                $config = config('webpush');

                $auth = [];

                if (! empty($config['vapid']['public_key']) && ! empty($config['vapid']['private_key'])) {
                    $auth['VAPID'] = [
                        'publicKey' => $config['vapid']['public_key'],
                        'privateKey' => $config['vapid']['private_key'],
                        'subject' => $config['vapid']['subject'] ?: url('/'),
                    ];
                }

                return (new WebPush(
                    $auth,
                    [],
                    Http::timeout(30)->withOptions($config['client_options'])->buildClient(),
                    new HttpFactory,
                    new HttpFactory,
                    null,
                    Log::channel(),
                ))
                    ->setReuseVAPIDHeaders(true)
                    ->setAutomaticPadding($config['automatic_padding']);
            });
    }
}
