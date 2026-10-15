<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('anyone can see how to get the Android app', function (bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get('/android')
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

    $this->get('/android')
        ->assertInertia(fn (Assert $page) => $page->where('androidApp.downloadUrl', 'https://downloads.example.no/manteion.apk'));
});

test('Android is told the app may open the sites links, signed with the configured keys', function () {
    config(['services.android.certificate_fingerprints' => ' ab:cd:ef , 12:34:56 ']);

    $this->get('/.well-known/assetlinks.json')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'no.manteion.app',
                'sha256_cert_fingerprints' => ['AB:CD:EF', '12:34:56'],
            ],
        ]]);
});

test('without a signing key there is nothing to vouch for', function (?string $fingerprints) {
    config(['services.android.certificate_fingerprints' => $fingerprints]);

    $this->get('/.well-known/assetlinks.json')->assertNotFound();
})->with([
    'not set' => [null],
    'empty' => [' , '],
]);
