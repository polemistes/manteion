<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventComment;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\Message;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
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
        $this->configureMorphMap();
        $this->configureDevCommands();
    }

    /**
     * `composer run dev` also starts the WebSocket server, and polls the
     * queue every second rather than every three, since push notifications
     * for new messages wait on it.
     */
    protected function configureDevCommands(): void
    {
        DevCommands::artisan('queue:listen --tries=3 --timeout=0 --sleep=1', 'queue');
        DevCommands::artisan('reverb:start', 'reverb');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // A relation first touched on one model of a loaded collection is
        // loaded for the whole collection, so the many `$row->user`-style
        // accesses in controllers cost one query per relation, not per row.
        Model::automaticallyEagerLoadRelationships();

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

    /**
     * Store short, stable discriminators for reportable models instead of
     * raw class names, both in the `reports` table and in the client-facing
     * API — never trust a client-supplied FQCN. Not `enforceMorphMap()`:
     * Laravel's own notifiable morph relation already uses raw class names
     * for unrelated models (e.g. `User`), and enforcing would break those.
     */
    protected function configureMorphMap(): void
    {
        Relation::morphMap([
            'message' => Message::class,
            'event' => Event::class,
            'comment' => EventComment::class,
            'leaflet' => Leaflet::class,
            'leaflet_response' => LeafletResponse::class,
        ]);
    }
}
