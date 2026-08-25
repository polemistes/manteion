<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('event'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'attendance_visibility' => ['required', Rule::enum(AttendanceVisibility::class)],
        ];
    }
}
