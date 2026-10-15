<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('anyone can see how to get the Android app', function (bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get('/app')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/Android')
            ->where('androidApp', [
                'downloadUrl' => 'https://github.com/polemistes/manteion/releases/latest/download/manteion.apk',
                'latestReleaseApi' => 'https://api.github.com/repos/polemistes/manteion/releases/latest',
            ]));
})->with([
    'a guest' => [false],
    'a signed-in user' => [true],
]);

test('the download comes from the configured address', function () {
    config(['services.android.download_url' => 'https://downloads.example.no/manteion.apk']);

    $this->get('/app')
        ->assertInertia(fn (Assert $page) => $page->where('androidApp.downloadUrl', 'https://downloads.example.no/manteion.apk'));
});
