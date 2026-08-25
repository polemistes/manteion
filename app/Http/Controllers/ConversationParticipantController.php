<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationParticipantRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationParticipantController extends Controller
{
    public function store(StoreConversationParticipantRequest $request, Conversation $conversation): RedirectResponse
    {
        ConversationParticipant::query()->firstOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $request->integer('user_id'),
        ]);

        return back();
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('leave', $conversation);

        $conversation->participantFor($request->user())?->delete();

        return redirect()->route('messages.index');
    }
}
