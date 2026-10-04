<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('every page says where the browser opens the WebSocket, without the secret', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'public-key',
        'broadcasting.connections.reverb.secret' => 'very-secret',
        'broadcasting.connections.reverb.client' => ['host' => 'manteion.example.no', 'port' => 443, 'scheme' => 'https'],
    ]);

    $this->actingAs(User::factory()->create())->get('/events')
        ->assertInertia(fn (Assert $page) => $page->where('reverb', [
            'key' => 'public-key',
            'host' => 'manteion.example.no',
            'port' => 443,
            'scheme' => 'https',
        ]))
        ->assertDontSee('very-secret');
});

test('without Reverb the browser gets no WebSocket settings', function () {
    config(['broadcasting.default' => 'log']);

    $this->actingAs(User::factory()->create())->get('/events')
        ->assertInertia(fn (Assert $page) => $page->where('reverb', null));
});

test('every page carries the servers clock for relative times', function () {
    Carbon::setTestNow('2026-10-04 12:00:00');

    $this->actingAs(User::factory()->create())->get('/events')
        ->assertInertia(fn (Assert $page) => $page->where('now', '2026-10-04T12:00:00+00:00'));
});
