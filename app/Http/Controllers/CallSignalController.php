<?php

namespace App\Http\Controllers;

use App\Enums\CallSignalType;
use App\Events\CallSignal;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallSignalController extends Controller
{
    /**
     * Passes one call set-up message (ring, offer, answer, candidates,
     * hangup…) to the other participant's browser, and to the sender's
     * other tabs, over their own channels. The server keeps nothing and
     * never sees the media: the call itself runs directly between the two
     * browsers.
     */
    public function store(Request $request, Conversation $conversation): Response
    {
        abort_unless(config('calls.enabled'), 404);

        Gate::authorize('call', $conversation);

        $validated = $request->validate([
            'call_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(CallSignalType::class)],
            'from_tab' => ['required', 'string', 'max:64'],
            'to_tab' => ['nullable', 'string', 'max:64'],
            'data' => ['nullable', 'array'],
        ]);

        broadcast(new CallSignal(
            conversation: $conversation,
            sender: $request->user(),
            type: CallSignalType::from($validated['type']),
            callId: $validated['call_id'],
            fromTab: $validated['from_tab'],
            toTab: $validated['to_tab'] ?? null,
            data: $validated['data'] ?? [],
        ));

        return response()->noContent();
    }
}
