<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportFacebookEventRequest;
use App\Services\FacebookEventImporter;
use Illuminate\Http\JsonResponse;

class EventFacebookImportController extends Controller
{
    /**
     * The details of a public Facebook event, for the new-event form to
     * fill itself in with. Nothing is saved: the event is made when the
     * form is. 404 when the event cannot be read (private, removed, or
     * Facebook would not answer).
     */
    public function __invoke(ImportFacebookEventRequest $request, FacebookEventImporter $importer): JsonResponse
    {
        $details = $importer->import($request->validated('url'));

        abort_if($details === null, 404);

        return response()->json($details);
    }
}
