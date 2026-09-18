<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReactionRequest;
use App\Models\Reaction;
use Illuminate\Http\RedirectResponse;

class ReactionController extends Controller
{
    /**
     * Toggles the viewer's reaction: the same emoji sent again removes it.
     */
    public function store(StoreReactionRequest $request): RedirectResponse
    {
        $reactable = $request->reactable();

        $attributes = [
            'user_id' => $request->user()->id,
            'reactable_type' => $reactable->getMorphClass(),
            'reactable_id' => $reactable->id,
            'emoji' => $request->validated('emoji'),
        ];

        $existing = Reaction::query()->where($attributes)->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            Reaction::query()->create($attributes);
        }

        return back();
    }
}
