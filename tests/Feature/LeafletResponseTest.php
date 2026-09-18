<?php

use App\Enums\UserRole;
use App\Models\Friendship;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\Report;
use App\Models\User;

test('anyone who can read a leaflet can respond to it', function () {
    $author = User::factory()->create();
    $friend = User::factory()->create();
    Friendship::factory()->accepted()->create(['requester_id' => $friend->id, 'addressee_id' => $author->id]);
    $leaflet = Leaflet::factory()->friends()->create(['user_id' => $author->id]);

    $this->actingAs($friend)->post("/leaflets/{$leaflet->id}/responses", ['body' => 'Sounds good!'])->assertRedirect();

    $this->assertDatabaseHas('leaflet_responses', ['leaflet_id' => $leaflet->id, 'user_id' => $friend->id, 'body' => 'Sounds good!']);

    $this->actingAs($author)->get('/leaflets')
        ->assertInertia(fn ($page) => $page
            ->where('leaflets.0.responses.0.body', 'Sounds good!')
            ->where('leaflets.0.responses.0.user.id', $friend->id)
            ->where('leaflets.0.responses.0.can_delete', true)
            ->where('leaflets.0.responses.0.reactions', []));
});

test('someone who cannot read the leaflet cannot respond', function () {
    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $leaflet = Leaflet::factory()->friends()->create(['user_id' => $author->id]);

    $this->actingAs($stranger)->post("/leaflets/{$leaflet->id}/responses", ['body' => 'Hi'])->assertForbidden();
});

test('a response can be deleted by its author, the leaflet author or staff, but no one else', function () {
    $author = User::factory()->create();
    $responder = User::factory()->create();
    $other = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $leaflet = Leaflet::factory()->official()->create(['user_id' => $author->id]);

    $byResponder = LeafletResponse::factory()->create(['leaflet_id' => $leaflet->id, 'user_id' => $responder->id]);
    $forAuthor = LeafletResponse::factory()->create(['leaflet_id' => $leaflet->id, 'user_id' => $responder->id]);
    $forStaff = LeafletResponse::factory()->create(['leaflet_id' => $leaflet->id, 'user_id' => $responder->id]);

    $this->actingAs($other)->delete("/leaflets/{$leaflet->id}/responses/{$byResponder->id}")->assertForbidden();
    $this->actingAs($responder)->delete("/leaflets/{$leaflet->id}/responses/{$byResponder->id}")->assertRedirect();
    $this->actingAs($author)->delete("/leaflets/{$leaflet->id}/responses/{$forAuthor->id}")->assertRedirect();
    $this->actingAs($moderator)->delete("/leaflets/{$leaflet->id}/responses/{$forStaff->id}")->assertRedirect();

    expect(LeafletResponse::query()->count())->toBe(0);
});

test('a response belongs to its leaflet in the url', function () {
    $user = User::factory()->create();
    $leaflet = Leaflet::factory()->create(['user_id' => $user->id]);
    $otherLeaflet = Leaflet::factory()->create(['user_id' => $user->id]);
    $response = LeafletResponse::factory()->create(['leaflet_id' => $otherLeaflet->id, 'user_id' => $user->id]);

    $this->actingAs($user)->delete("/leaflets/{$leaflet->id}/responses/{$response->id}")->assertNotFound();
});

test('a response can be reported and deleting it resolves the report', function () {
    $author = User::factory()->create();
    $responder = User::factory()->create();
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $leaflet = Leaflet::factory()->official()->create(['user_id' => $author->id]);
    $response = LeafletResponse::factory()->create(['leaflet_id' => $leaflet->id, 'user_id' => $responder->id, 'body' => 'Rude']);

    $this->actingAs($author)->post('/reports', ['reportable_type' => 'leaflet_response', 'reportable_id' => $response->id])->assertRedirect();
    $report = Report::query()->firstOrFail();

    $this->actingAs($moderator)->get('/moderation/reports')
        ->assertInertia(fn ($page) => $page
            ->where('reports.0.reportable.type', 'leaflet_response')
            ->where('reports.0.reportable.excerpt', 'Rude'));

    $this->actingAs($moderator)->delete("/leaflets/{$leaflet->id}/responses/{$response->id}")->assertRedirect();
    expect($report->fresh()->status->value)->toBe('resolved');
});
