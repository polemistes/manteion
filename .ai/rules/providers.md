---
paths:
  - app/Providers/AppServiceProvider.php
---

# Providers

## WebPush needs a logger bound, or tests explode
This machine has neither the GMP nor BCMath PHP extension. `minishlink/web-push`'s `WebPush` constructor calls `Utils::checkRequirement()`, which `trigger_error()`s an E_USER_NOTICE recommending one of them whenever no `LoggerInterface` was passed in — and Laravel's test environment escalates that notice into a hard failure, breaking any test that dispatches a notification with the `webpush` channel (even without a real subscription in the DB).

Fixed via `AppServiceProvider::configureWebPushLogger()`, which rebinds `WebPush::class` (contextual on `WebPushChannel::class`) to pass `Log::channel()` as the logger, so the notice becomes a normal log entry instead of a PHP notice. This also means production won't spam raw PHP notices on every push send. If this binding is ever removed, either restore it or install `php-bcmath`/`php-gmp` system-wide.

## Use Relation::morphMap(), not enforceMorphMap()
This app's `notifications` table (Laravel's built-in Notifiable morph relation) already stores raw FQCNs like `App\Models\User`, unregistered in any morph map. `Relation::enforceMorphMap([...])` throws `ClassMorphViolationException` the moment any unmapped model (e.g. `User::notifications()`) touches a morph relation — it broke `HandleInertiaRequests::share()` on every request. Use plain `Relation::morphMap([...])` (registers aliases without forbidding others) whenever adding new polymorphic relations here.
