<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\EventComment;
use App\Models\Message;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    /**
     * @var array<string, class-string>
     */
    private const REPORTABLE_TYPES = [
        'message' => Message::class,
        'event' => Event::class,
        'comment' => EventComment::class,
    ];

    public function authorize(): bool
    {
        $reportable = $this->reportable();

        if ($reportable === null) {
            return false;
        }

        return $this->user()->can('view', $reportable) && ! $this->user()->is($this->reportableOwner($reportable));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reportable_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::REPORTABLE_TYPES))],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reportable(): Message|Event|EventComment|null
    {
        $type = $this->string('reportable_type')->value();
        $modelClass = self::REPORTABLE_TYPES[$type] ?? null;

        if ($modelClass === null || ! $this->filled('reportable_id')) {
            return null;
        }

        return $modelClass::find($this->integer('reportable_id'));
    }

    private function reportableOwner(Message|Event|EventComment $reportable): mixed
    {
        return match (true) {
            $reportable instanceof Message => $reportable->sender,
            $reportable instanceof Event => $reportable->owner,
            $reportable instanceof EventComment => $reportable->user,
        };
    }
}
