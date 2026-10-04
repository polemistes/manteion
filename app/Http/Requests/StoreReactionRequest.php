<?php

namespace App\Http\Requests;

use App\Models\EventComment;
use App\Models\Leaflet;
use App\Models\LeafletResponse;
use App\Models\Message;
use App\Rules\Emoji;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReactionRequest extends FormRequest
{
    /**
     * @var array<string, class-string>
     */
    private const REACTABLE_TYPES = [
        'comment' => EventComment::class,
        'leaflet' => Leaflet::class,
        'leaflet_response' => LeafletResponse::class,
        'message' => Message::class,
    ];

    public function authorize(): bool
    {
        $reactable = $this->reactable();

        if ($reactable === null) {
            return false;
        }

        // Staff may read any conversation for moderation, but reacting is
        // taking part, which only participants do.
        if ($reactable instanceof Message) {
            return $reactable->conversation->isVisibleTo($this->user());
        }

        // Likes on comments: anyone who can see the event, but not on a
        // deleted comment.
        if ($reactable instanceof EventComment) {
            return ! $reactable->is_deleted && $reactable->event->isVisibleTo($this->user());
        }

        return $this->user()->can('view', $reactable);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reactable_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::REACTABLE_TYPES))],
            'reactable_id' => ['required', 'integer'],
            'emoji' => ['required', 'string', 'max:64', new Emoji],
        ];
    }

    public function reactable(): EventComment|Leaflet|LeafletResponse|Message|null
    {
        $modelClass = self::REACTABLE_TYPES[$this->string('reactable_type')->value()] ?? null;

        if ($modelClass === null || ! $this->filled('reactable_id')) {
            return null;
        }

        return $modelClass::find($this->integer('reactable_id'));
    }
}
