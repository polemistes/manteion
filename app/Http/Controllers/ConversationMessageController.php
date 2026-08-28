<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ConversationMessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $conversation->participantFor($request->user())?->update(['last_read_at' => now()]);

        return back();
    }

    public function destroy(Conversation $conversation, Message $message): RedirectResponse
    {
        Gate::authorize('delete', $message);

        $message->update(['body' => '', 'is_deleted' => true]);

        return back();
    }
}
