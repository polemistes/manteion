<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
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

        return back();
    }
}
