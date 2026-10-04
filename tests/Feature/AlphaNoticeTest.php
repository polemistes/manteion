<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the alpha notice shows until the browser has dismissed it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/events')
        ->assertInertia(fn (Assert $page) => $page->where('alphaNoticeDismissed', false));
});

test('a dismissed alpha notice stays hidden on the server-rendered page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withUnencryptedCookie('alpha_notice_dismissed', '1')
        ->get('/events')
        ->assertInertia(fn (Assert $page) => $page->where('alphaNoticeDismissed', true));
});
