<?php

namespace App\Http\Controllers;

use App\Events\BadgesUpdated;
use App\Events\ConversationRead;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConversationReadController extends Controller
{
    /**
     * The open thread saw new messages arrive while visible. The user's
     * other tabs and devices hear about it through their badges.
     */
    public function store(Request $request, Conversation $conversation): Response
    {
        $participant = $conversation->participantFor($request->user());

        abort_if($participant === null, 403);

        $participant->markRead();

        BadgesUpdated::dispatch($participant->user_id);
        ConversationRead::dispatch($participant);

        return response()->noContent();
    }
}
