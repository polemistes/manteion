---
paths:
  - 'lang/**'
---

# Lang

## laravel-lang/publisher does not support Laravel 13 yet
`laravel-lang/lang` (the raw translation data package) installs fine on Laravel 13, but `laravel-lang/publisher` (the `lang:add {locale}` Artisan command that normally generates `lang/{locale}/*.php` from it) currently caps at `illuminate/console ^12.0` and also pulls in `dragon-code/support`, which needs `ext-bcmath` — neither is available here, so `composer require laravel-lang/publisher` fails.

Workaround used for `lang/nb/validation.php`: read the translated strings directly from `vendor/laravel-lang/lang/locales/{locale}/php.json` (flat, dot-notated keys matching `lang/en/validation.php`'s structure) and build the nested PHP array by hand/script. Cross-check the result against `lang/en/validation.php`'s literal values — some entries in that data are themselves untranslated English placeholders (not omitted), so a naive copy silently leaves English text under a non-English locale. `auth.php`/`pagination.php`/`passwords.php` have too few keys to be worth this treatment; just hand-translate those directly.

Before repeating this, check whether `laravel-lang/publisher` has added Laravel 13 support (`composer show laravel-lang/publisher` after a fresh `composer require --dry-run`) — if so, prefer the real `lang:add` command instead.
