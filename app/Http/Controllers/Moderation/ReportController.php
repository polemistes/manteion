<?php

namespace App\Http\Controllers\Moderation;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\Leaflet;
use App\Models\Message;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $reports = Report::query()
            ->where('status', ReportStatus::Pending)
            ->with(['reporter', 'reportable'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $summaries = $reports->getCollection()
            ->filter(fn (Report $report) => $report->reportable !== null)
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'reason' => $report->reason,
                'created_at' => $report->created_at->toIso8601String(),
                'reporter' => [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->name,
                    'username' => $report->reporter->username,
                ],
                'reportable' => $this->summarizeReportable($report),
            ])
            ->values();

        return Inertia::render('moderation/Index', [
            'reports' => $summaries,
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
                'previous_url' => $reports->previousPageUrl(),
                'next_url' => $reports->nextPageUrl(),
            ],
        ]);
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $report->update([
            'status' => ReportStatus::Dismissed,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summarizeReportable(Report $report): ?array
    {
        $reportable = $report->reportable;

        return match (true) {
            $reportable instanceof Message => [
                'type' => 'message',
                'excerpt' => $reportable->is_deleted ? null : $reportable->body,
                'is_deleted' => $reportable->is_deleted,
                'author' => $this->summarizeUser($reportable->sender),
                'url' => route('messages.show', $reportable->conversation_id),
                'delete_url' => route('messages.messages.destroy', [$reportable->conversation_id, $reportable->id]),
            ],
            $reportable instanceof Event => [
                'type' => 'event',
                'excerpt' => $reportable->is_deleted ? null : $reportable->name,
                'is_deleted' => $reportable->is_deleted,
                'author' => $this->summarizeUser($reportable->owner),
                'url' => route('events.show', $reportable->id),
                'delete_url' => route('events.destroy', $reportable->id),
            ],
            $reportable instanceof EventComment => [
                'type' => 'comment',
                'excerpt' => $reportable->is_deleted ? null : $reportable->body,
                'is_deleted' => $reportable->is_deleted,
                'author' => $this->summarizeUser($reportable->user),
                'url' => route('events.show', $reportable->event_id),
                'delete_url' => route('events.comments.destroy', [$reportable->event_id, $reportable->id]),
            ],
            $reportable instanceof Leaflet => [
                'type' => 'leaflet',
                'excerpt' => $reportable->body,
                'is_deleted' => false,
                'author' => $this->summarizeUser($reportable->owner),
                'url' => route('leaflets.show', $reportable->id),
                'delete_url' => route('leaflets.destroy', $reportable->id),
            ],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeUser(mixed $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
        ];
    }
}
