<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\Report;
use App\Models\User;

test('a moderator sees pending reports in the queue', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $event = Event::factory()->create();
    $comment = EventComment::factory()->for($event)->create(['user_id' => $author->id, 'body' => 'Reported text']);
    Report::factory()->for($reporter, 'reporter')->create([
        'reportable_type' => (new EventComment)->getMorphClass(),
        'reportable_id' => $comment->id,
        'reason' => 'Spam',
    ]);

    $response = $this->actingAs($moderator)->get('/moderation/reports');

    $response->assertInertia(fn ($page) => $page
        ->has('reports', 1)
        ->where('reports.0.reason', 'Spam')
        ->where('reports.0.reportable.excerpt', 'Reported text')
        ->where('reports.0.reportable.author.id', $author->id));
});

test('a moderator can dismiss a report without deleting the content', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $reporter = User::factory()->create();
    $event = Event::factory()->create();
    $comment = EventComment::factory()->for($event)->create();
    $report = Report::factory()->for($reporter, 'reporter')->create([
        'reportable_type' => (new EventComment)->getMorphClass(),
        'reportable_id' => $comment->id,
    ]);

    $this->actingAs($moderator)->patch("/moderation/reports/{$report->id}")->assertRedirect();

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed);
    expect(EventComment::find($comment->id))->not->toBeNull();
});

test('a regular member cannot access the moderation queue', function () {
    $member = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($member)->get('/moderation/reports')->assertForbidden();
});

test('staff can message the reporter or the reported user from the queue', function () {
    $moderator = User::factory()->create(['role' => UserRole::Moderator]);
    $author = User::factory()->create();

    $response = $this->actingAs($moderator)->post("/moderation/users/{$author->id}/message");

    $response->assertRedirect();
});
