<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageImageController extends Controller
{
    /**
     * The picture attached to a message, for the people in the
     * conversation (and staff, as for the thread itself). The files sit on
     * the private disk and have no address of their own, so this is the
     * only way to one. A browser may keep it for good: the address names
     * one message, and a deleted picture never comes back under it.
     */
    public function show(Conversation $conversation, Message $message): StreamedResponse
    {
        Gate::authorize('view', $conversation);

        $disk = Storage::disk(Message::IMAGE_DISK);

        abort_if($message->image_path === null || ! $disk->exists($message->image_path), 404);

        return $disk->response($message->image_path, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
