<?php

use App\Enums\ThemeMode;
use App\Models\User;

test('every page links the web app manifest and the home screen metadata', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('content="width=device-width, initial-scale=1, viewport-fit=cover"', false)
        ->assertSee('<meta name="mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-status-bar-style" content="default">', false)
        ->assertSee('<meta name="apple-mobile-web-app-title" content="'.config('app.name').'">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/icons/apple-touch-icon-180.png">', false)
        ->assertSee('<meta name="csrf-token" content="', false);
});

test('the browser colour follows the system, or the mode the user picked', function (ThemeMode $mode, string $light, string $dark) {
    $user = User::factory()->create(['theme_mode' => $mode]);

    $this->actingAs($user)->get('/events')
        ->assertSuccessful()
        ->assertSee('<meta name="theme-color" media="(prefers-color-scheme: light)" content="'.$light.'">', false)
        ->assertSee('<meta name="theme-color" media="(prefers-color-scheme: dark)" content="'.$dark.'">', false);
})->with([
    'system' => [ThemeMode::System, '#FFFFFF', '#131211'],
    'light' => [ThemeMode::Light, '#FFFFFF', '#FFFFFF'],
    'dark' => [ThemeMode::Dark, '#131211', '#131211'],
]);

test('the manifest makes the app installable, with icons of the sizes it claims', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toBe('Manteion')
        ->short_name->toBe('Manteion')
        ->start_url->toBe(route('events.index', absolute: false))
        ->scope->toBe('/')
        ->display->toBe('standalone')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toBe(['any', 'any', 'maskable'])
        ->and(collect($manifest['shortcuts'])->pluck('url')->all())->toBe([
            route('events.create', absolute: false),
            route('messages.index', absolute: false),
        ]);

    foreach ($manifest['icons'] as $icon) {
        $image = getimagesize(public_path(ltrim($icon['src'], '/')));

        expect("{$image[0]}x{$image[1]}")->toBe($icon['sizes'])
            ->and($image['mime'])->toBe($icon['type']);
    }
});

test('the service worker finds its offline page and notification icons', function () {
    $worker = file_get_contents(public_path('sw.js'));

    foreach (['/offline.html', '/icons/icon-192.png', '/icons/badge-96.png'] as $path) {
        expect($worker)->toContain("'{$path}'")
            ->and(public_path(ltrim($path, '/')))->toBeFile();
    }

    [$badgeWidth, $badgeHeight] = getimagesize(public_path('icons/badge-96.png'));
    [$touchWidth, $touchHeight] = getimagesize(public_path('icons/apple-touch-icon-180.png'));

    expect([$badgeWidth, $badgeHeight])->toBe([96, 96])
        ->and([$touchWidth, $touchHeight])->toBe([180, 180]);
});
