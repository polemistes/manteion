<?php

namespace App\Http\Controllers;

use App\Enums\AdminAlert;
use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use App\Services\AdminAlerts;
use Illuminate\Http\RedirectResponse;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request): RedirectResponse
    {
        $reportable = $request->reportable();

        Report::query()->firstOrCreate([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->id,
        ], [
            'reason' => $request->validated('reason'),
        ]);

        AdminAlerts::send(AdminAlert::ReportFiled, $request->user(), [
            'reporter' => $request->user()->name,
            'what' => $reportable->getMorphClass(),
            'reason' => $request->validated('reason'),
        ], route('moderation.reports.index'));

        return back();
    }
}
