<?php

use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Tag;
use App\Models\User;

test('a user can set and replace their tag preference for a friend', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);
    $music = Tag::factory()->create();
    $sports = Tag::factory()->create();

    $this->actingAs($user)
        ->put("/contacts/{$friend->id}/tag-preferences", ['tag_ids' => [$music->id]])
        ->assertRedirect();

    $this->assertDatabaseHas('contact_tag_preferences', ['user_id' => $user->id, 'contact_user_id' => $friend->id, 'tag_id' => $music->id]);

    $this->actingAs($user)
        ->put("/contacts/{$friend->id}/tag-preferences", ['tag_ids' => [$sports->id]])
        ->assertRedirect();

    $this->assertDatabaseMissing('contact_tag_preferences', ['user_id' => $user->id, 'contact_user_id' => $friend->id, 'tag_id' => $music->id]);
    $this->assertDatabaseHas('contact_tag_preferences', ['user_id' => $user->id, 'contact_user_id' => $friend->id, 'tag_id' => $sports->id]);
});

test('a user cannot set a tag preference for someone who is neither a friend nor followed', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $tag = Tag::factory()->create();

    $this->actingAs($user)
        ->put("/contacts/{$stranger->id}/tag-preferences", ['tag_ids' => [$tag->id]])
        ->assertForbidden();
});

test('a user can set a tag preference for someone they follow', function () {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    Follow::factory()->create(['follower_id' => $user->id, 'followed_id' => $followed->id]);
    $tag = Tag::factory()->create();

    $this->actingAs($user)
        ->put("/contacts/{$followed->id}/tag-preferences", ['tag_ids' => [$tag->id]])
        ->assertRedirect();
});

test('a user can mute a friend entirely, stored as a null tag_id row', function () {
    $user = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);
    $music = Tag::factory()->create();

    $this->actingAs($user)
        ->put("/contacts/{$friend->id}/tag-preferences", ['tag_ids' => [$music->id]])
        ->assertRedirect();

    $this->actingAs($user)
        ->put("/contacts/{$friend->id}/tag-preferences", ['mute_all' => true])
        ->assertRedirect();

    $this->assertDatabaseHas('contact_tag_preferences', ['user_id' => $user->id, 'contact_user_id' => $friend->id, 'tag_id' => null]);
    $this->assertDatabaseMissing('contact_tag_preferences', ['user_id' => $user->id, 'contact_user_id' => $friend->id, 'tag_id' => $music->id]);
});
