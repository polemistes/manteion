<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesPrivateImages;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageImageController extends Controller
{
    use ServesPrivateImages;

    /**
     * The picture attached to a message, for the people in the
     * conversation (and staff, as for the thread itself). A deleted
     * picture never comes back under its address.
     */
    public function show(Conversation $conversation, Message $message): StreamedResponse
    {
        Gate::authorize('view', $conversation);

        return $this->privateImage($message->image_path);
    }
}
